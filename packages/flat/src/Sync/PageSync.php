<?php

namespace Pushword\Flat\Sync;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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

final readonly class PageSync
{
    public function __construct(
        private AppPool $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        private PageImporter $pageImporter,
        private PageExporter $pageExporter,
        private RedirectionExporter $redirectionExporter,
        private RedirectionImporter $redirectionImporter,
        private PageRepository $pageRepository,
        private EntityManagerInterface $entityManager,
    ) {
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

        // 3. Import all .md files
        $this->importDirectory($contentDir);
        $this->pageImporter->finishImport();

        // 4. Delete pages that no longer have .md files (excluding redirections from CSV)
        $this->deleteMissingPages($app->getMainHost());
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
    }

    public function mustImport(?string $host = null): bool
    {
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        return $this->hasNewerFiles($contentDir, $app->getMainHost());
    }

    private function importDirectory(string $dir): void
    {
        if (! file_exists($dir)) {
            return;
        }

        /** @var string[] $files */
        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }

            // Skip backup files (ending with ~)
            if (str_ends_with($file, '~')) {
                continue;
            }

            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->importDirectory($path);

                continue;
            }

            $lastEditDateTime = new DateTime()->setTimestamp(filemtime($path));
            $this->pageImporter->import($path, $lastEditDateTime);
        }
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

        return $lastEditDateTime > $page->getUpdatedAt();
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

            // Page exists in DB but no .md file or redirection.csv entry - delete it
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
}
