<?php

namespace Pushword\Flat;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Utils\Entity;
use Pushword\Flat\Exporter\PageExporter;

use function Safe\json_encode;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Permit to find error in image or link.
 */
final class FlatFileExporter
{
    private string $copyMedia = '';

    public string $exportDir = '';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $mediaDir,
        private readonly AppPool $apps,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly MediaRepository $mediaRepo,
        private readonly PageExporter $pageExporter,
        private readonly Stopwatch $stopWatch
    ) {
        $this->filesystem = new Filesystem();
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
        $this->exportMedias();

        return $this->stopWatch->stop('run')->getDuration();
    }

    private function exportMedias(): void
    {
        $medias = $this->mediaRepo->findAll();

        foreach ($medias as $media) {
            $this->exportMedia($media);
        }
    }

    private function exportMedia(Media $media): void
    {
        $properties = Entity::getProperties($media);

        $data = [];
        foreach ($properties as $property) {
            if (\in_array($property, ['id', 'hash'], true)) {
                continue;
            }

            $getter = 'get'.ucfirst($property);
            $data[$property] = $media->$getter(); // @phpstan-ignore-line
        }

        if ('' !== $this->copyMedia && '0' !== $this->copyMedia) {
            $destination = $this->exportDir.'/'.$this->copyMedia.'/'.$media->getMedia();
            $this->filesystem->copy($media->getPath(), $destination);
        }

        $jsonContent = json_encode($data, \JSON_PRETTY_PRINT);
        $jsonFile = ('' !== $this->copyMedia && '0' !== $this->copyMedia ? $this->exportDir.'/'.$this->copyMedia : $this->mediaDir).'/'.$media->getMedia().'.json';
        $this->filesystem->dumpFile($jsonFile, $jsonContent);
    }

    public function setCopyMedia(string $copyMedia): void
    {
        $this->copyMedia = $copyMedia;
    }
}
