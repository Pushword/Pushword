<?php

namespace Pushword\Core\Image;

use Exception;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Vips\Core as VipsCore;
use Intervention\Image\Drivers\Vips\Driver as VipsDriver;
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
        // decodeAnimation must stay true: intervention/image 4.0.4 has a bug where
        // RemoveAnimationModifier clears the source Imagick object before the decoder
        // reads its mime type, causing "Failed to retrieve image media type" on the
        // Imagick driver. We strip animation manually after decode instead.
        $this->interventionManager = new InterventionImageManager($driverClass, decodeAnimation: true);
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
                $driver = new VipsDriver();
                $driver->checkHealth();

                // Verify multi-format encoding works (ensureInMemory uses VipsArea gtype)
                $mgr = new InterventionImageManager(VipsDriver::class, decodeAnimation: true);
                $encoded = $mgr->createImage(1, 1)->encodeUsingFileExtension('jpg');
                $testImage = $mgr->decode($encoded->toString());
                VipsCore::ensureInMemory($testImage->core());

                return ['vips', VipsDriver::class];
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
            && class_exists(VipsDriver::class);
    }

    /**
     * @return class-string
     */
    private function driverClassFromName(string $name): string
    {
        if ('vips' === $name && $this->isVipsClassAvailable()) {
            return VipsDriver::class;
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

        // Strip animation here (after decode) instead of via the decoder's
        // decodeAnimation:false path, which is broken in intervention/image 4.0.4.
        if ($image->isAnimated()) {
            $image = $image->removeAnimation();
        }

        return $image;
    }

    public function getResolvedDriver(): string
    {
        return $this->resolvedDriver;
    }
}
