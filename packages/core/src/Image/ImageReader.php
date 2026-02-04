<?php

namespace Pushword\Core\Image;

use Exception;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager as InterventionImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;

final readonly class ImageReader
{
    private InterventionImageManager $interventionManager;

    private string $resolvedDriver;

    public function __construct(
        private MediaStorageAdapter $mediaStorage,
        private string $imageDriver = 'auto',
    ) {
        $this->resolvedDriver = 'auto' !== $this->imageDriver
            ? $this->imageDriver
            : match (true) {
                $this->isVipsAvailable() => 'vips',
                \extension_loaded('imagick') => 'imagick',
                default => 'gd',
            };

        $this->interventionManager = new InterventionImageManager($this->createDriver($this->resolvedDriver));
    }

    private function isVipsAvailable(): bool
    {
        return \extension_loaded('ffi')
            && class_exists(\Intervention\Image\Drivers\Vips\Driver::class);
    }

    private function createDriver(string $name): DriverInterface
    {
        if ('vips' === $name && $this->isVipsAvailable()) {
            return new \Intervention\Image\Drivers\Vips\Driver();
        }

        return match ($name) {
            'imagick' => new ImagickDriver(),
            default => new GdDriver(),
        };
    }

    public function read(Media|string $media): ImageInterface
    {
        $path = $media instanceof Media
            ? $this->mediaStorage->getLocalPath($media->getFileName())
            : $media;

        try {
            return $this->interventionManager->read($path);
        } catch (Exception) {
            throw new Exception($this->resolvedDriver.' cannot read image `'.$path.'`');
        }
    }

    public function getResolvedDriver(): string
    {
        return $this->resolvedDriver;
    }
}
