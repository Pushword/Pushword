<?php

declare(strict_types=1);

namespace Pushword\Core\Image;

use Exception;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Vips\Core as VipsCore;
use Intervention\Image\ImageManager as InterventionImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;
use Throwable;

final readonly class ImageReader
{
    private InterventionImageManager $interventionManager;

    private string $resolvedDriver;

    public function __construct(
        private MediaStorageAdapter $mediaStorage,
        private string $imageDriver = 'auto',
    ) {
        [$this->resolvedDriver, $driverClass] = $this->resolveDriver();
        $this->interventionManager = new InterventionImageManager($driverClass, decodeAnimation: false);
    }

    /**
     * @return array{string, class-string}
     */
    private function resolveDriver(): array
    {
        if ('auto' !== $this->imageDriver) {
            return [$this->imageDriver, $this->driverClassFromName($this->imageDriver)];
        }

        if ($this->isVipsClassAvailable()) {
            try {
                $driver = new \Intervention\Image\Drivers\Vips\Driver();
                $driver->checkHealth();

                // Verify multi-format encoding works (ensureInMemory uses VipsArea gtype)
                $mgr = new InterventionImageManager(\Intervention\Image\Drivers\Vips\Driver::class, decodeAnimation: false);
                $encoded = $mgr->createImage(1, 1)->encodeUsingFileExtension('jpg');
                $testImage = $mgr->decode($encoded->toString());
                VipsCore::ensureInMemory($testImage->core());

                return ['vips', \Intervention\Image\Drivers\Vips\Driver::class];
            } catch (Throwable) {
                // vips not usable (e.g. ffi.enable=preload, broken gtype), fall through
            }
        }

        if (\extension_loaded('imagick')) {
            return ['imagick', ImagickDriver::class];
        }

        return ['gd', GdDriver::class];
    }

    private function isVipsClassAvailable(): bool
    {
        return \extension_loaded('ffi')
            && class_exists(\Intervention\Image\Drivers\Vips\Driver::class);
    }

    /**
     * @return class-string
     */
    private function driverClassFromName(string $name): string
    {
        if ('vips' === $name && $this->isVipsClassAvailable()) {
            return \Intervention\Image\Drivers\Vips\Driver::class;
        }

        return match ($name) {
            'imagick' => ImagickDriver::class,
            default => GdDriver::class,
        };
    }

    public function read(Media|string $media): ImageInterface
    {
        $path = $media instanceof Media
            ? $this->mediaStorage->getLocalPath($media->getFileName())
            : $media;

        try {
            $image = $this->interventionManager->decode($path);
        } catch (Exception) {
            throw new Exception($this->resolvedDriver.' cannot read image `'.$path.'`');
        }

        // Vips loads images in sequential mode by default which causes
        // "out of order read" errors when applying transformations.
        // Copy into memory to allow random access.
        if ($image->core() instanceof VipsCore) {
            VipsCore::ensureInMemory($image->core());
        }

        return $image;
    }

    public function getResolvedDriver(): string
    {
        return $this->resolvedDriver;
    }
}
