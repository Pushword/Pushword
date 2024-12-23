<?php

namespace Pushword\Flat;

use DateTime;
use LogicException;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\Importer\AbstractImporter;
use Pushword\Flat\Importer\MediaImporter;
use Pushword\Flat\Importer\PageImporter;

use function Safe\filemtime;
use function Safe\scandir;

/**
 * Permit to find error in image or link.
 *
 * @template T of object
 */
final class FlatFileImporter
{
    protected AppConfig $app;

    protected string $customMediaDir = '';

    public function __construct(
        protected string $projectDir,
        protected string $mediaDir,
        protected AppPool $apps,
        protected FlatFileContentDirFinder $contentDirFinder,
        protected PageImporter $pageImporter,
        protected MediaImporter $mediaImporter
    ) {
    }

    public function run(?string $host): void
    {
        if (null !== $host) {
            $this->app = $this->apps->switchCurrentApp($host)->get();
        }

        $contentDir = $this->contentDirFinder->get($this->app->getMainHost());

        $this->importFiles($this->mediaDir, 'media');
        $this->mediaImporter->finishImport();

        $this->importFiles('' !== $this->customMediaDir && '0' !== $this->customMediaDir ? $contentDir.$this->customMediaDir : $contentDir.'/media', 'media');
        $this->mediaImporter->finishImport();

        $this->importFiles($contentDir, 'page');
        $this->pageImporter->finishImport();
    }

    public function setMediaCustomDir(string $dir): void
    {
        $this->customMediaDir = $dir;
    }

    public function setMediaDir(string $dir): void
    {
        $this->mediaImporter->mediaDir = $dir;
    }

    private function importFiles(string $dir, string $type): void
    {
        if (! file_exists($dir)) {
            return;
        }

        /** @var string[] */
        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }

            if (is_dir($dir.'/'.$file)) {
                $this->importFiles($dir.'/'.$file, $type);

                continue;
            }

            $this->importFile($dir.'/'.$file, $type);
        }
    }

    private function importFile(string $filePath, string $type): void
    {
        $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($filePath));

        $this->getImporter($type)->import($filePath, $lastEditDateTime);
    }

    /**
     * @return AbstractImporter<T>
     */
    private function getImporter(string $type): AbstractImporter
    {
        $importer = $type.'Importer';

        if (! property_exists($this, $importer)
            || ! ($importer = $this->$importer) instanceof AbstractImporter) { // @phpstan-ignore-line
            throw new LogicException();
        }

        return $importer;
    }
}
