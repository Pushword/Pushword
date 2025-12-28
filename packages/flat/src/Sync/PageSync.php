<?php

namespace Pushword\Flat\Sync;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Repository\PageRepository;
use Pushword\Flat\Exporter\PageExporter;
use Pushword\Flat\Exporter\RedirectionExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\PageImporter;
use Pushword\Flat\Importer\RedirectionImporter;

use function Safe\filemtime;
use function Safe\scandir;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class PageSync
{
    private int $deletedCount = 0;

    private ?OutputInterface $output = null;

    public function __construct(
        private readonly AppPool $apps,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly PageImporter $pageImporter,
        private readonly PageExporter $pageExporter,
        private readonly RedirectionExporter $redirectionExporter,
        private readonly RedirectionImporter $redirectionImporter,
        private readonly PageRepository $pageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
        $this->pageExporter->setOutput($output);
    }

    public function setStopwatch(?Stopwatch $stopwatch): void
    {
        // Stopwatch passed for interface consistency but not used in PageSync
    }

    public function sync(?string $host = null, bool $forceExport = false, ?string $exportDir = null): void
    {
        if (! $forceExport && $this->mustImport($host)) {
            $this->import($host);

            return;
        }

        $this->export($host, $forceExport, $exportDir);
    }

    public function import(?string $host = null): void
    {
        $this->deletedCount = 0;
        $app = $this->resolveApp($host);
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
        $totalFiles = \count($files);

        if ($totalFiles > 0) {
            $this->output?->writeln(\sprintf('Importing %d pages...', $totalFiles));
        }

        // 4. Import all .md files with progress
        $currentFile = 0;
        foreach ($files as $path) {
            ++$currentFile;
            $relativePath = str_replace($contentDir.'/', '', $path);
            $this->output?->writeln(\sprintf('[%d/%d] Importing %s', $currentFile, $totalFiles, $relativePath));

            $lastEditDateTime = new DateTime()->setTimestamp(filemtime($path));
            $this->pageImporter->import($path, $lastEditDateTime);
        }

        $this->pageImporter->finishImport();

        // 5. Delete pages that no longer have .md files (excluding redirections from CSV)
        $this->deleteMissingPages($app->getMainHost());

        // 6. Regenerate index.csv to reflect the current database state
        $this->regenerateIndex($contentDir);
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
     * Regenerate index.csv and markdown files after import to ensure they reflect current database state.
     * This writes back auto-generated IDs to markdown files that were imported without IDs.
     * Files are only written if their content actually changed (smart update).
     */
    private function regenerateIndex(string $contentDir): void
    {
        $this->pageExporter->exportDir = $contentDir;
        $this->pageExporter->exportPages();
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

        $lastEditDateTime = new DateTime()->setTimestamp(filemtime($filePath));

        return $lastEditDateTime > $page->updatedAt;
    }

    /**
     * Delete pages that exist in DB but have no corresponding .md file or redirection.csv entry.
     */
    private function deleteMissingPages(string $host): void
    {
        // Only delete if we imported at least one page
        if (! $this->pageImporter->hasImportedPages() && ! $this->redirectionImporter->hasIndexData()) {
            return;
        }

        $importedSlugs = $this->pageImporter->getImportedSlugs();
        $redirectionSlugs = $this->redirectionImporter->getImportedSlugs();

        // Find all pages for this host
        $allPages = $this->pageRepository->findByHost($host);

        foreach ($allPages as $page) {
            // Skip if page was imported from .md file
            if (\in_array($page->getSlug(), $importedSlugs, true)) {
                continue;
            }

            // Skip if page is a redirection imported from redirection.csv
            if (\in_array($page->getSlug(), $redirectionSlugs, true)) {
                continue;
            }

            $this->logger?->info('Deleting page `'.$page->getSlug().'`');
            $this->output?->writeln(\sprintf('<comment>Deleting page %s</comment>', $page->getSlug()));
            ++$this->deletedCount;
            $this->entityManager->remove($page);
        }

        $this->entityManager->flush();
    }

    private function resolveApp(?string $host): AppConfig
    {
        return null !== $host
            ? $this->apps->switchCurrentApp($host)->get()
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
