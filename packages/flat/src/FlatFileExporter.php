<?php

namespace Pushword\Flat;

use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\Exporter\PageExporter;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Permit to find error in image or link.
 */
final class FlatFileExporter
{
    public string $copyMedia = ''; // ???

    public string $exportDir = '';

    public function __construct(
        private readonly string $projectDir,
        private readonly AppPool $apps,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly PageExporter $pageExporter,
        private readonly MediaExporter $mediaExporter,
        private readonly Stopwatch $stopWatch
    ) {
    }

    public function run(string $host, bool $force = false): int|float
    {
        $this->stopWatch->start('run');

        $app = $this->apps->switchCurrentApp($host)->get();

        $this->exportDir = '' !== $this->exportDir ? $this->exportDir
            : ($this->contentDirFinder->has($app->getMainHost())
                ? $this->contentDirFinder->get($app->getMainHost())
                : $this->projectDir.'/var/export/'.uniqid());

        $this->pageExporter->exportDir = $this->exportDir;
        $this->pageExporter->exportPages($force);

        $this->mediaExporter->exportDir = $this->exportDir;
        $this->mediaExporter->copyMedia = $this->copyMedia;
        $this->mediaExporter->exportMedias();

        return $this->stopWatch->stop('run')->getDuration();
    }

    // can't find where it's used
    public function setCopyMedia(string $copyMedia): void
    {
        $this->copyMedia = $copyMedia;
    }
}
