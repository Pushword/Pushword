<?php

namespace Pushword\Flat;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Utils\Entity;
use Pushword\Flat\Importer\MediaImporter;
use Pushword\Flat\Importer\PageImporter;

use function Safe\json_encode;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Permit to find error in image or link.
 */
class FlatFileExporter
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    protected AppConfig $app;

    protected string $copyMedia = '';

    protected string $exportDir = '';

    protected Filesystem $filesystem;

    public function __construct(
        protected string $projectDir,
        protected string $mediaDir,
        protected AppPool $apps,
        protected FlatFileContentDirFinder $contentDirFinder,
        protected PageImporter $pageImporter,
        protected MediaImporter $mediaImporter,
        protected PageRepository $pageRepo,
        protected MediaRepository $mediaRepo,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function setExportDir(string $path): self
    {
        $this->exportDir = $path;

        return $this;
    }

    public function run(?string $host): string
    {
        if (null !== $host) {
            $this->app = $this->apps->switchCurrentApp($host)->get();
        }

        $this->exportDir = '' !== $this->exportDir ? $this->exportDir
            : ($this->contentDirFinder->has($this->app->getMainHost())
                ? $this->contentDirFinder->get($this->app->getMainHost())
                : $this->projectDir.'/var/export/'.uniqid());

        $this->exportPages();
        $this->exportMedias();

        return $this->exportDir;
    }

    private function exportPages(): void
    {
        $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());

        foreach ($pages as $page) {
            $this->exportPage($page);
        }
    }

    private function exportPage(Page $page): void
    {
        $properties = Entity::getProperties($page);

        $data = [];
        foreach ($properties as $property) {
            if (\in_array($property, ['mainContent', 'id'], true)) {
                continue;
            }

            $getter = 'get'.ucfirst($property);
            $value = $page->$getter(); // @phpstan-ignore-line
            if (null === $value) {
                continue;
            }

            if ('customProperties' == $property && empty($value)) { // @phpstan-ignore-line
                continue;
            }

            $data[$property] = $value;
        }

        $metaData = Yaml::dump($data);
        $content = '---'.\PHP_EOL.$metaData.\PHP_EOL.'---'.\PHP_EOL.\PHP_EOL.$page->getMainContent();

        $this->filesystem->dumpFile($this->exportDir.'/'.$page->getSlug().'.md', $content);
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
