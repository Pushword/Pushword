<?php

namespace Pushword\Core\Image;

use InvalidArgumentException;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Rotates the source (master) image in place and rebuilds every cached variant,
 * following the same path as a fresh upload.
 */
final readonly class ImageRotator
{
    /** Re-encode quality for the rewritten master. */
    private const int QUALITY = 90;

    public function __construct(
        private ImageReader $imageReader,
        private ImageEncoder $imageEncoder,
        private ImageCacheManager $imageCacheManager,
        private ImageCacheGenerator $imageCacheGenerator,
        private MediaStorageAdapter $mediaStorage,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Rotate the master image clockwise by a multiple of 90 degrees.
     *
     * The caller is responsible for flushing the entity afterwards (dimensions,
     * mainColor, size and hash are updated in memory here).
     */
    public function rotate(Media $media, int $degrees): void
    {
        if (! $media->isImage()) {
            throw new InvalidArgumentException('Only images can be rotated.');
        }

        $clockwise = ((($degrees % 360) + 360) % 360);
        if (! \in_array($clockwise, [90, 180, 270], true)) {
            throw new InvalidArgumentException('Rotation must be 90, 180 or 270 degrees.');
        }

        $image = $this->imageReader->read($media);
        // Intervention Image v4 rotates clockwise for positive angles (see its
        // RotateModifier), which matches our clockwise API — pass it as-is.
        $image->rotate($clockwise);

        $fileName = $media->getFileName();
        $binary = $this->imageEncoder->encodeOriginalToString($image, self::QUALITY, $media);

        $this->mediaStorage->write($fileName, $binary);

        // For remote storage getLocalPath() returns a cached download; refresh it
        // so the cache rebuild below reads the rotated pixels, not the stale copy.
        if (! $this->mediaStorage->isLocal()) {
            $this->filesystem->dumpFile($this->mediaStorage->getLocalPath($fileName), $binary);
        }

        $media->setSize(\strlen($binary));
        $media->setHash(sha1($binary, true));

        // Same rebuild as an upload: drop the stale cache, regenerate a quick
        // preview (also refreshes dimensions + mainColor from the rotated master)
        // and queue the full variant regeneration in the background.
        $this->imageCacheManager->remove($media);
        $this->imageCacheGenerator->generateQuickPreview($media);
        $this->imageCacheGenerator->runBackgroundCacheGeneration($fileName);
    }
}
