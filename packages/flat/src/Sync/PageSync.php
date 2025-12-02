<?php

namespace Pushword\Flat\Sync;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Repository\PageRepository;
use Pushword\Flat\Exporter\PageExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\PageImporter;

use function Safe\filemtime;
use function Safe\scandir;

final readonly class PageSync
{
    public function __construct(
        private AppPool $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        private PageImporter $pageImporter,
        private PageExporter $pageExporter,
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

        $this->pageImporter->resetImport();

        // 2. Import all .md files
        $this->importDirectory($contentDir);
        $this->pageImporter->finishImport();

        // 3. Delete pages that no longer have .md files
        $this->deleteMissingPages($app->getMainHost());
    }

    public function export(?string $host = null, bool $force = false, ?string $exportDir = null): void
    {
        $app = $this->resolveApp($host);
        $targetDir = $exportDir ?? $this->contentDirFinder->get($app->getMainHost());

        $this->pageExporter->exportDir = $targetDir;
        $this->pageExporter->exportPages($force);
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

            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->importDirectory($path);

                continue;
            }

            $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($path));
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

        $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($filePath));

        return $lastEditDateTime > $page->getUpdatedAt();
    }

    /**
     * Delete pages that exist in DB but have no corresponding .md file.
     */
    private function deleteMissingPages(string $host): void
    {
        // Only delete if we imported at least one page
        if (! $this->pageImporter->hasImportedPages()) {
            return;
        }

        $importedSlugs = $this->pageImporter->getImportedSlugs();

        // Find all pages for this host
        $allPages = $this->pageRepository->findByHost($host);

        foreach ($allPages as $page) {
            if (! \in_array($page->getSlug(), $importedSlugs, true)) {
                // Page exists in DB but no .md file was found - delete it
                $this->entityManager->remove($page);
            }
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
