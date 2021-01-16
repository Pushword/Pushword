<?php

namespace Pushword\Flat;

use DateTime;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\Importer\MediaImporter;
use Pushword\Flat\Importer\PageImporter;

/**
 * Permit to find error in image or link.
 */
class FlatFileImporter
{
    protected AppConfig $app;
    protected AppPool $apps;
    protected string $projectDir;
    protected FlatFileContentDirFinder $contentDirFinder;
    protected PageImporter $pageImporter;
    protected MediaImporter $mediaImporter;

    public function __construct(
        string $projectDir,
        AppPool $apps,
        FlatFileContentDirFinder $contentDirFinder,
        PageImporter $pageImporter,
        MediaImporter $mediaImporter
    ) {
        $this->projectDir = $projectDir;
        $this->apps = $apps;
        $this->contentDirFinder = $contentDirFinder;
        $this->pageImporter = $pageImporter;
        $this->mediaImporter = $mediaImporter;
    }

    public function run(?string $host): void
    {
        $this->app = $this->apps->switchCurrentApp($host)->get();

        $contentDir = $this->contentDirFinder->get($this->app->getMainHost());
        $this->importFiles($this->projectDir.'/media', 'media');
        $this->importFiles(file_exists($contentDir.'/media/default') ? $contentDir.'/media/default' : $contentDir.'/media', 'media');
        $this->importFiles($contentDir, 'page');
        $this->mediaImporter->finishImport();
        $this->pageImporter->finishImport();
    }

    private function importFiles($dir, string $type): void
    {
        if (! file_exists($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'])) {
                continue;
            }
            if (is_dir($dir.'/'.$file)) {
                $this->importFiles($dir.'/'.$file, $type);

                continue;
            }

            $this->importFile($dir.'/'.$file, $type);
        }
    }

    private function importFile($filePath, $type)
    {
        $lastEdit = (new DateTime())->setTimestamp(filemtime($filePath));

        $importer = $type.'Importer';

        return $this->$importer->import($filePath, $lastEdit);
    }
}
