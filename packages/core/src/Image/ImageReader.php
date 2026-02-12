<?php

namespace Pushword\Core\Image;

use Exception;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Vips\Core as VipsCore;
use Intervention\Image\ImageManager as InterventionImageManager;
use Intervention\Image\Interfaces\DriverInterface;
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
        [$this->resolvedDriver, $driver] = $this->resolveDriver();
        $this->interventionManager = new InterventionImageManager($driver, decodeAnimation: false);
    }

    /**
     * @return array{string, DriverInterface}
     */
    private function resolveDriver(): array
    {
        if ('auto' !== $this->imageDriver) {
            return [$this->imageDriver, $this->createDriver($this->imageDriver)];
        }

        if ($this->isVipsClassAvailable()) {
            try {
                $driver = new \Intervention\Image\Drivers\Vips\Driver();
                $driver->checkHealth();

                // Verify multi-format encoding works (ensureInMemory uses VipsArea gtype)
                $mgr = new InterventionImageManager($driver, decodeAnimation: false);
                $encoded = $mgr->create(1, 1)->encodeByExtension('jpg');
                $testImage = $mgr->read($encoded->toString());
                VipsCore::ensureInMemory($testImage->core());

                return ['vips', $driver];
            } catch (Throwable) {
                // vips not usable (e.g. ffi.enable=preload, broken gtype), fall through
            }
        }

        if (\extension_loaded('imagick')) {
            return ['imagick', new ImagickDriver()];
        }

        return ['gd', new GdDriver()];
    }

    private function isVipsClassAvailable(): bool
    {
        return \extension_loaded('ffi')
            && class_exists(\Intervention\Image\Drivers\Vips\Driver::class);
    }

    private function createDriver(string $name): DriverInterface
    {
        if ('vips' === $name && $this->isVipsClassAvailable()) {
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
            $image = $this->interventionManager->read($path);
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
