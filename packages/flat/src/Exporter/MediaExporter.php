<?php

namespace Pushword\Flat\Exporter;

use DateTimeInterface;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Utils\Entity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final class MediaExporter
{
    public string $copyMedia = ''; // ???

    public string $exportDir = '';

    private readonly Filesystem $filesystem;

    private readonly ExporterDefaultValueHelper $defaultValue;

    public function __construct(
        private readonly MediaRepository $mediaRepo,
        private readonly string $mediaDir,
    ) {
        $this->filesystem = new Filesystem();
        $this->defaultValue = new ExporterDefaultValueHelper();
    }

    public function exportMedias(): void
    {
        $medias = $this->mediaRepo->findAll();

        foreach ($medias as $media) {
            $this->exportMedia($media);
        }
    }

    /**
     * @return scalar|null
     */
    private function getValue(string $property, Media $media): mixed
    {
        if (in_array($property, ['media', 'ratioLabel', 'ratio', 'width', 'height'], true)) {
            return null;
        }

        $getter = 'get'.ucfirst($property);
        $value = $media->$getter(); // @phpstan-ignore-line

        if ('storeIn' === $property) {
            return $media->getStoreIn() === $this->mediaDir ? null : $media->getStoreIn();
        }

        if (null === $value) {
            return null;
        }

        if ($value === $this->defaultValue->get($property, Media::class)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            // return null for createdAt and updatedAt
            // it will take the date of first import for creation
            // and last sync for update
            return null;
        }

        assert(is_scalar($value));

        return $value;
    }

    private function exportMedia(Media $media): void
    {
        $properties = Entity::getProperties($media);

        $data = [];
        foreach ($properties as $property) {
            if (\in_array($property, ['id', 'hash'], true)) {
                continue;
            }

            $value = $this->getValue($property, $media);
            if (null === $value) {
                continue;
            }

            $data[$property] = $value;
        }

        if ('' !== $this->copyMedia && '0' !== $this->copyMedia) {
            if (! file_exists($media->getPath())) {
                throw new Exception('Media file not found: '.$media->getPath());
            }

            $destination = $this->exportDir.'/'.$this->copyMedia.'/'.$media->getFileName();
            $this->filesystem->copy($media->getPath(), $destination);
        }

        $yamlData = Yaml::dump($data, indent: 2);
        $yamlFilePath = $this->getYamlFileDir().'/'.$media->getFileName().'.yaml';
        $this->filesystem->dumpFile($yamlFilePath, $yamlData);
    }

    private function getYamlFileDir(): string
    {
        if ('' !== $this->copyMedia && '0' !== $this->copyMedia) {
            return $this->exportDir.'/'.$this->copyMedia;
        }

        return $this->mediaDir;
    }
}
