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
    /** @var AppConfig */
    protected $app;
    /** @var AppPool */
    protected $apps;
    protected $webDir;
    /** @var FlatFileContentDirFinder */
    protected $contentDirFinder;
    /** @var PageImporter */
    protected $pageImporter;
    /** @var MediaImporter */
    protected $mediaImporter;

    public function __construct(
        string $webDir,
        AppPool $apps,
        FlatFileContentDirFinder $contentDirFinder,
        PageImporter $pageImporter,
        MediaImporter $mediaImporter
    ) {
        $this->webDir = $webDir;
        $this->apps = $apps;
        $this->contentDirFinder = $contentDirFinder;
        $this->pageImporter = $pageImporter;
        $this->mediaImporter = $mediaImporter;
    }

    public function run(?string $host)
    {
        $this->app = $this->apps->switchCurrentApp($host)->get();

        $this->importFiles($this->webDir.'/../media', 'media');
        $this->importFiles($this->contentDirFinder->get($this->app->getMainHost()), 'page');
        $this->mediaImporter->finishImport();
        $this->pageImporter->finishImport();
    }

    private function importFiles($dir, string $type)
    {
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
