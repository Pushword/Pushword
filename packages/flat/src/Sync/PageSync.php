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
        // Stopwatch passed for interface consistency but not used in PageSync
    }

    public function sync(?string $host = null, bool $forceExport = false, ?string $exportDir = null, bool $skipId = false): void
    {
        if (! $forceExport && $this->mustImport($host)) {
            $this->import($host, $skipId);

            return;
        }

        $this->export($host, $forceExport, $exportDir, $skipId);
    }

    public function import(?string $host = null, bool $skipId = false, bool $force = false): void
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
        $this->regenerateIndex($contentDir, $skipId, $allPages);

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
     * Write back auto-generated IDs to imported markdown files and regenerate index CSV.
     * Only re-exports pages that were actually imported (not the full dataset).
     *
     * @param Page[] $allPages Pre-loaded pages from the import cycle
     */
    private function regenerateIndex(string $contentDir, bool $skipId, array $allPages): void
    {
        $this->pageExporter->exportDir = $contentDir;
        $importedSlugs = $this->pageImporter->getImportedSlugs();
        $this->pageExporter->exportPagesSubset($importedSlugs, $skipId, $allPages);
    }

    public function export(?string $host = null, bool $force = false, ?string $exportDir = null, bool $skipId = false): void
    {
        $app = $this->resolveApp($host);
        $targetDir = $exportDir ?? $this->contentDirFinder->get($app->getMainHost());

        // Export pages (.md files + index.csv + iDraft.csv)
        $this->pageExporter->exportDir = $targetDir;
        $this->pageExporter->exportPages($force, $skipId);

        // Export redirections (redirection.csv)
        $this->redirectionExporter->exportDir = $targetDir;
        $this->redirectionExporter->exportRedirections();

        // Record export in sync state
        $this->stateManager->recordExport('page', $app->getMainHost());
    }

    public function mustImport(?string $host = null): bool
    {
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        return $this->hasNewerFiles($contentDir, $app->getMainHost());
    }

    private function hasNewerFiles(string $dir, string $host): bool
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
                if ($this->hasNewerFiles($path, $host)) {
                    return true;
                }

                continue;
            }

            if ($this->isFileNewer($path, $host)) {
                return true;
            }
        }

        return false;
    }

    private function isFileNewer(string $filePath, string $host): bool
    {
        if (! str_ends_with($filePath, '.md')) {
            return false;
        }

        $document = $this->pageImporter->getDocumentFromFile($filePath);
        if (null === $document) {
            return true;
        }

        $slug = $this->pageImporter->getSlug($filePath, $document);
        $page = $this->pageImporter->pageRepo->findOneBy([
            'slug' => $slug,
            'host' => $host,
        ]);

        if (null === $page) {
            return true;
        }

        $lastEditDateTime = new DateTime()->setTimestamp((int) filemtime($filePath));

        // Check for conflicts using the last sync time
        $lastSyncTime = $this->stateManager->getLastSyncTime('page', $host);
        if ($lastSyncTime > 0) {
            $lastSyncAt = new DateTime('@'.$lastSyncTime);
            $conflict = $this->conflictResolver->resolvePageConflict(
                $page,
                $filePath,
                $lastEditDateTime,
                $lastSyncAt,
            );

            if ($conflict['hasConflict']) {
                // If there's a conflict, flat wins means we should import
                return 'flat' === $conflict['winner'];
            }
        }

        return $lastEditDateTime > $page->updatedAt;
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
