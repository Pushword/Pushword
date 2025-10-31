<?php

namespace Pushword\Flat;

use DateTimeInterface;
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
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Yaml\Yaml;

/**
 * Permit to find error in image or link.
 */
final class FlatFileExporter
{
    protected AppConfig $app;

    protected string $copyMedia = '';

    public string $exportDir = '';

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
        private Stopwatch $stopWatch
    ) {
        $this->filesystem = new Filesystem();
    }

    public function setExportDir(string $path): self
    {
        $this->exportDir = $path;

        return $this;
    }

    public function run(string $host): int|float
    {
        $this->stopWatch->start('run');

        $app = $this->apps->switchCurrentApp($host)->get();

        $this->exportDir = '' !== $this->exportDir ? $this->exportDir
            : ($this->contentDirFinder->has($app->getMainHost())
                ? $this->contentDirFinder->get($app->getMainHost())
                : $this->projectDir.'/var/export/'.uniqid());

        $this->exportPages();
        $this->exportMedias();

        return $this->stopWatch->stop('run')->getDuration();
    }

    private function exportPages(): void
    {
        $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());

        foreach ($pages as $page) {
            // compare the filemtime with the page->getUpdatedAt()
            $this->exportPage($page);
        }
    }

    /** @var array<string, mixed> */
    private array $defaultValueCache = [];

    private function getPageDefaultValue(string $property): mixed
    {
        if (isset($this->defaultValueCache[$property])) {
            return $this->defaultValueCache[$property];
        }

        $reflection = new \ReflectionClass(Page::class);
        $reflectionProperty = $reflection->getProperty($property);
        $this->defaultValueCache[$property] = $reflectionProperty->getDefaultValue();

        return $this->defaultValueCache[$property];
    }

    private function exportPage(Page $page): void
    {
        $exportFilePath = $this->exportDir.'/'.$page->getSlug().'.md';
        if (file_exists($exportFilePath) && filemtime($exportFilePath) >= $page->safegetUpdatedAt()->getTimestamp()) {
            return;
        }

        $properties = ['title', 'h1', 'slug', 'id'] + Entity::getProperties($page);

        $data = [];
        foreach ($properties as $property) {
            if (\in_array($property, ['mainContent'], true)) {
                continue;
            }

            $getter = 'get'.ucfirst($property);
            $value = $page->$getter(); // @phpstan-ignore-line
            if (null === $value) {
                continue;
            }

            if (
                in_array($property, ['customProperties', 'tags'], true)
                && in_array($value, [null, [], ''], true)
            ) {
                continue;
            }

            if ($value === $this->getPageDefaultValue($property)) {
                continue;
            }

            if (in_array($property, ['createdAt', 'updatedAt', 'host', 'slug'], true)) {
                continue;
            }

            if ('locale' === $property && $value === $this->apps->get()->getLocale()) {
                continue;
            }

            $data[$property] = $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : $value;
        }

        $metaData = Yaml::dump($data, indent: 2);
        $content = '---'.\PHP_EOL.$metaData.'---'.\PHP_EOL.\PHP_EOL.$page->getMainContent();

        $this->filesystem->dumpFile($exportFilePath, $content);
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
