<?php

namespace Pushword\Flat;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Utils\Entity;
use Pushword\Flat\Importer\MediaImporter;
use Pushword\Flat\Importer\PageImporter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Permit to find error in image or link.
 */
class FlatFileExporter
{
    protected AppConfig $app;
    protected AppPool $apps;
    protected string $projectDir;
    protected string $mediaDir;
    protected string $copyMedia = '';
    protected string $exportDir = '';
    protected string $mediaClass;
    protected string $pageClass;
    protected FlatFileContentDirFinder $contentDirFinder;
    protected PageImporter $pageImporter;
    protected MediaImporter $mediaImporter;
    protected EntityManagerInterface $entityManager;
    protected Filesystem $filesystem;

    public function __construct(
        string $projectDir,
        string $mediaDir,
        string $pageClass,
        string $mediaClass,
        AppPool $apps,
        EntityManagerInterface $entityManager,
        FlatFileContentDirFinder $contentDirFinder,
        PageImporter $pageImporter,
        MediaImporter $mediaImporter
    ) {
        $this->projectDir = $projectDir;
        $this->mediaDir = $mediaDir;
        $this->pageClass = $pageClass;
        $this->mediaClass = $mediaClass;
        $this->entityManager = $entityManager;
        $this->apps = $apps;
        $this->contentDirFinder = $contentDirFinder;
        $this->pageImporter = $pageImporter;
        $this->mediaImporter = $mediaImporter;
        $this->filesystem = new Filesystem();
    }

    public function setExportDir(string $path): self
    {
        $this->exportDir = $path;

        return $this;
    }

    public function run(?string $host): string
    {
        $this->app = $this->apps->switchCurrentApp($host)->get();

        $this->exportDir = $this->exportDir ?: ($this->contentDirFinder->has($this->app->getMainHost())
                ? $this->contentDirFinder->get($this->app->getMainHost())
                : $this->projectDir.'/var/export/'.uniqid());

        $this->exportPages();
        $this->exportMedias();

        return $this->exportDir;
    }

    private function exportPages(): void
    {
        $repo = Repository::getPageRepository($this->entityManager, $this->pageClass);
        $pages = $repo->findByHost($this->apps->get()->getMainHost(), $this->apps->get()->isFirstApp());

        foreach ($pages as $page) {
            $this->exportPage($page);
        }
    }

    private function exportPage(PageInterface $page): void
    {
        $properties = Entity::getProperties($page);

        $data = [];
        foreach ($properties as $property) {
            if (\in_array($property, ['mainContent', 'id'])) {
                continue;
            }
            $getter = 'get'.ucfirst($property);
            $value = $page->$getter();
            if (null === $value
            || ('customProperties' == $property && empty($value))) {
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
        $repo = Repository::getMediaRepository($this->entityManager, $this->mediaClass);
        $medias = $repo->findAll();

        foreach ($medias as $media) {
            $this->exportMedia($media);
        }
    }

    private function exportMedia(MediaInterface $media): void
    {
        $properties = Entity::getProperties($media);

        $data = [];
        foreach ($properties as $property) {
            if (\in_array($property, ['id'])) {
                continue;
            }
            $getter = 'get'.ucfirst($property);
            $data[$property] = $media->$getter();
        }

        if ($this->copyMedia) {
            $destination = $this->exportDir.'/'.$this->copyMedia.'/'.$media->getMedia();
            $this->filesystem->copy($media->getPath(), $destination);
        }

        $jsonContent = json_encode($data, \JSON_PRETTY_PRINT);
        $jsonFile = ($this->copyMedia ? $this->exportDir.'/'.$this->copyMedia : $this->mediaDir).'/'.$media->getMedia().'.json';
        $this->filesystem->dumpFile($jsonFile, $jsonContent);
    }

    public function setCopyMedia(string $copyMedia): void
    {
        $this->copyMedia = $copyMedia;
    }
}
