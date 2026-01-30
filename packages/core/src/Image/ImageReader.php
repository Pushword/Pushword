<?php

namespace Pushword\Core\Image;

use Exception;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager as InterventionImageManager;
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
        $this->resolvedDriver = 'auto' === $this->imageDriver
            ? (\extension_loaded('imagick') ? 'imagick' : 'gd')
            : $this->imageDriver;

        $driver = 'imagick' === $this->resolvedDriver ? new ImagickDriver() : new GdDriver();
        $this->interventionManager = new InterventionImageManager($driver);
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
