<?php

declare(strict_types=1);

namespace Pushword\Flat\Sync;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Exporter\PageExporter;
use Pushword\Flat\Exporter\RedirectionExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\PageImporter;
use Pushword\Flat\Importer\RedirectionImporter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class PageSync
{
    private int $deletedCount = 0;

    private ?OutputInterface $output = null;

    private ?Stopwatch $stopwatch = null;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly PageImporter $pageImporter,
        private readonly PageExporter $pageExporter,
        private readonly RedirectionExporter $redirectionExporter,
        private readonly RedirectionImporter $redirectionImporter,
        private readonly PageRepository $pageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SyncStateManager $stateManager,
        private readonly ConflictResolver $conflictResolver,
        /** @var string[] */
        private readonly array $excludeFiles = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
        $this->pageExporter->setOutput($output);
        $this->conflictResolver->setOutput($output);
    }

    public function setStopwatch(?Stopwatch $stopwatch): void
    {
        $this->stopwatch = $stopwatch;
    }

    public function sync(?string $host = null, bool $forceExport = false, ?string $exportDir = null): void
    {
        $this->stopwatch?->start('page.detection');
        $shouldImport = ! $forceExport && $this->mustImport($host);
        $this->stopwatch?->stop('page.detection');

        if ($shouldImport) {
            $this->import($host);

            return;
        }

        $this->export($host, $forceExport, $exportDir);
    }

    public function import(?string $host = null, bool $force = false): void
    {
        $this->deletedCount = 0;
        $app = $this->resolveApp($host);

        if ($force) {
            $this->resetHostPages($app->getMainHost());
        }

        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        // 1. Import redirections from redirection.csv
        $this->redirectionImporter->reset();
        $this->redirectionImporter->loadIndex($contentDir);
        if ($this->redirectionImporter->hasIndexData()) {
            $this->redirectionImporter->importAll();
        }

        // 2. Reset page importer
        $this->pageImporter->resetImport();

        // 3. Collect all .md files first for progress display
        $files = $this->collectMarkdownFiles($contentDir);

        // 4. Import all .md files with progress (only show actually imported files)
        foreach ($files as $path) {
            $lastEditDateTime = new DateTime()->setTimestamp((int) filemtime($path));
            $imported = $this->pageImporter->import($path, $lastEditDateTime);

            if ($imported) {
                $relativePath = str_replace($contentDir.'/', '', $path);
                $this->output?->writeln(\sprintf('Imported %s', $relativePath));
            }
        }

        $this->pageImporter->finishImport();

        // Reuse pages loaded by finishImport() to avoid extra findByHost queries
        $allPages = $this->pageImporter->getLoadedPages() ?? $this->pageRepository->findByHost($app->getMainHost());

        // 5. Delete pages that no longer have .md files (excluding redirections from CSV)
        $this->deleteMissingPages($allPages);

        // 6. Regenerate index.csv to reflect the current database state
        $this->regenerateIndex($contentDir, $allPages);

        // 7. Record import in sync state
        $this->stateManager->recordImport('page', $app->getMainHost());
    }

    /**
     * Collect all markdown files recursively from a directory.
     *
     * @return string[]
     */
    private function collectMarkdownFiles(string $dir): array
    {
        if (! file_exists($dir)) {
            return [];
        }

        $files = [];

        /** @var string[] $entries */
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if (\in_array($entry, ['.', '..'], true)) {
                continue;
            }

            // Skip backup files (ending with ~)
            if (str_ends_with($entry, '~')) {
                continue;
            }

            // Skip conflict backup files
            if (str_contains($entry, '~conflict-')) {
                continue;
            }

            // Skip excluded files
            if (\in_array($entry, $this->excludeFiles, true)) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                $files = [...$files, ...$this->collectMarkdownFiles($path)];

                continue;
            }

            if (str_ends_with($path, '.md')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Regenerate index CSV and re-export imported pages.
     * Only re-exports pages that were actually imported (not the full dataset).
     *
     * @param Page[] $allPages Pre-loaded pages from the import cycle
     */
    private function regenerateIndex(string $contentDir, array $allPages): void
    {
        $this->pageExporter->exportDir = $contentDir;
        $importedSlugs = $this->pageImporter->getImportedSlugs();
        $this->pageExporter->exportPagesSubset($importedSlugs, $allPages);
    }

    public function export(?string $host = null, bool $force = false, ?string $exportDir = null): void
    {
        $app = $this->resolveApp($host);
        $targetDir = $exportDir ?? $this->contentDirFinder->get($app->getMainHost());

        // Export pages (.md files + index.csv + iDraft.csv)
        $this->pageExporter->exportDir = $targetDir;
        $this->pageExporter->exportPages($force);

        // Export redirections (redirection.csv)
        $this->redirectionExporter->exportDir = $targetDir;
        $this->redirectionExporter->exportRedirections();

        // Record export in sync state
        $this->stateManager->recordExport('page', $app->getMainHost());
    }

    public function mustImport(?string $host = null): bool
    {
        $app = $this->resolveApp($host);
        $mainHost = $app->getMainHost();
        $contentDir = $this->contentDirFinder->get($mainHost);
        $lastSyncTime = $this->stateManager->getLastSyncTime('page', $mainHost);

        // Fast path: when we have a sync baseline, use filemtime only (no YAML parsing)
        if ($lastSyncTime > 0) {
            return $this->hasNewerFilesFast($contentDir, $lastSyncTime);
        }

        // Slow path (first sync): full YAML-based detection with slug matching
        $pages = $this->pageRepository->findByHost($mainHost);
        $slugIndex = [];
        foreach ($pages as $page) {
            $slugIndex[$page->getSlug()] = $page;
        }

        $lastSyncTime = $this->stateManager->getLastSyncTime('page', $mainHost);

        return $this->hasNewerFiles($contentDir, $mainHost, $slugIndex, $lastSyncTime);
    }

    /**
     * Fast direction detection using only filemtime comparison.
     * Used when lastSyncTime > 0 (not first sync).
     */
    private function hasNewerFilesFast(string $dir, int $lastSyncTime): bool
    {
        if (! file_exists($dir)) {
            return false;
        }

        /** @var string[] $entries */
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if (\in_array($entry, ['.', '..'], true)) {
                continue;
            }

            if (\in_array($entry, $this->excludeFiles, true)) {
                continue;
            }

            if (str_ends_with($entry, '~')) {
                continue;
            }

            if (str_contains($entry, '~conflict-')) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                if ($this->hasNewerFilesFast($path, $lastSyncTime)) {
                    return true;
                }

                continue;
            }

            if (! str_ends_with($entry, '.md') && ! str_ends_with($entry, '.csv')) {
                continue;
            }

            if (filemtime($path) > $lastSyncTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, Page> $slugIndex
     */
    private function hasNewerFiles(string $dir, string $host, array $slugIndex, int $lastSyncTime): bool
    {
        if (! file_exists($dir)) {
            return false;
        }

        /** @var string[] $files */
        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }

            if (\in_array($file, $this->excludeFiles, true)) {
                continue;
            }

            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                if ($this->hasNewerFiles($path, $host, $slugIndex, $lastSyncTime)) {
                    return true;
                }

                continue;
            }

            if ($this->isFileNewer($path, $host, $slugIndex, $lastSyncTime)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, Page> $slugIndex
     */
    private function isFileNewer(string $filePath, string $host, array $slugIndex, int $lastSyncTime): bool
    {
        if (str_ends_with($filePath, RedirectionExporter::INDEX_FILE)) {
            return $this->isCsvFileNewer($filePath, $lastSyncTime);
        }

        if (! str_ends_with($filePath, '.md')) {
            return false;
        }

        // Fast path: if file hasn't changed since last sync, skip expensive YAML parsing
        if ($lastSyncTime > 0 && (int) filemtime($filePath) <= $lastSyncTime) {
            return false;
        }

        $document = $this->pageImporter->getDocumentFromFile($filePath);
        if (null === $document) {
            return true;
        }

        $slug = $this->pageImporter->getSlug($filePath, $document);
        $page = $slugIndex[$slug] ?? null;

        if (null === $page) {
            return true;
        }

        $lastEditDateTime = new DateTime()->setTimestamp((int) filemtime($filePath));

        // Check for conflicts using the last sync time
        if ($lastSyncTime > 0) {
            $lastSyncAt = new DateTime('@'.$lastSyncTime);
            $bothModified = $lastEditDateTime > $lastSyncAt && $page->updatedAt > $lastSyncAt;

            // Only read content when both sides modified (expensive, skip for one-sided changes)
            $fileContent = null;
            $dbContent = null;
            if ($bothModified) {
                $readResult = @file_get_contents($filePath);
                $fileContent = false !== $readResult ? $readResult : null;
                $dbContent = $this->pageExporter->generatePageContent($page);
            }

            $conflict = $this->conflictResolver->resolvePageConflict(
                $page,
                $filePath,
                $lastEditDateTime,
                $lastSyncAt,
                $fileContent,
                $dbContent,
            );

            if ($conflict['hasConflict']) {
                return 'flat' === $conflict['winner'];
            }
        }

        return $lastEditDateTime > $page->updatedAt;
    }

    private function isCsvFileNewer(string $filePath, int $lastSyncTime): bool
    {
        if (0 === $lastSyncTime) {
            return true;
        }

        return filemtime($filePath) > $lastSyncTime;
    }

    /**
     * Delete all pages for the host (used for force import reset).
     */
    private function resetHostPages(string $host): void
    {
        $pages = $this->pageRepository->findByHost($host);
        $count = \count($pages);

        if (0 === $count) {
            return;
        }

        $this->output?->writeln(\sprintf('<comment>Resetting %d pages for host %s...</comment>', $count, $host));

        foreach ($pages as $page) {
            $this->entityManager->remove($page);
            ++$this->deletedCount;
        }

        $this->entityManager->flush();
    }

    /**
     * Delete pages that exist in DB but have no corresponding .md file or redirection.csv entry.
     *
     * @param Page[] $allPages Pre-loaded pages for this host
     */
    private function deleteMissingPages(array $allPages): void
    {
        // Only delete if we imported at least one page
        if (! $this->pageImporter->hasImportedPages() && ! $this->redirectionImporter->hasIndexData()) {
            return;
        }

        $importedSlugSet = array_flip($this->pageImporter->getImportedSlugs());
        $redirectionSlugSet = array_flip($this->redirectionImporter->getImportedSlugs());

        foreach ($allPages as $page) {
            $slug = $page->getSlug();

            // Skip if page was imported from .md file
            if (isset($importedSlugSet[$slug])) {
                continue;
            }

            // Skip if page is a redirection imported from redirection.csv
            if (isset($redirectionSlugSet[$slug])) {
                continue;
            }

            $this->logger?->info('Deleting page `'.$slug.'`');
            $this->output?->writeln(\sprintf('<comment>Deleting page %s</comment>', $slug));
            ++$this->deletedCount;
            $this->entityManager->remove($page);
        }

        $this->entityManager->flush();
    }

    private function resolveApp(?string $host): SiteConfig
    {
        return null !== $host
            ? $this->apps->switchSite($host)->get()
            : $this->apps->get();
    }

    public function getImportedCount(): int
    {
        return $this->pageImporter->getImportedCount();
    }

    public function getSkippedCount(): int
    {
        return $this->pageImporter->getSkippedCount();
    }

    public function getDeletedCount(): int
    {
        return $this->deletedCount;
    }

    public function getExportedCount(): int
    {
        return $this->pageExporter->getExportedCount();
    }

    public function getExportSkippedCount(): int
    {
        return $this->pageExporter->getSkippedCount();
    }
}
