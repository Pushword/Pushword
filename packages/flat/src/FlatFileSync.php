<?php

namespace Pushword\Flat;

use DateTime;
use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\Importer\PageImporter;

use function Safe\filemtime;
use function Safe\scandir;

final readonly class FlatFileSync
{
    public function __construct(
        private AppPool $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        private PageImporter $pageImporter,
    ) {
    }

    public function mustImport(?string $host): bool
    {
        $app = null !== $host
          ? $this->apps->switchCurrentApp($host)->get()
          : $this->apps->get();

        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        // Scan all content files to check if any are newer than their DB counterparts
        return $this->hasNewerFiles($contentDir, $app->getMainHost());
    }

    private function hasNewerFiles(string $dir, string $host): bool
    {
        if (! file_exists($dir)) {
            return false;
        }

        /** @var string[] */
        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }

            $filePath = $dir.'/'.$file;
            if (is_dir($filePath)) {
                if ($this->hasNewerFiles($filePath, $host)) {
                    return true;
                }

                continue;
            }

            if ($this->isFileNewer($filePath, $host)) {
                return true;
            }
        }

        return false;
    }

    private function isFileNewer(string $filePath, string $host): bool
    {
        // Only process markdown files (pages)
        if (! str_ends_with($filePath, '.md')) {
            return false;
        }

        $document = $this->pageImporter->getDocumentFromFile($filePath);

        if (null === $document) {
            return true;
        }

        $slug = $this->pageImporter->getSlug($filePath, $document);

        $page = $this->pageImporter->pageRepo->findOneBy(['slug' => $slug, 'host' => $host]);

        if (null === $page) {
            return true; // Page doesn't exist in DB, needs to be imported
        }

        // Compare modification time: if file is newer, we need to import

        $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($filePath));

        return $lastEditDateTime > $page->getUpdatedAt();
    }
}
