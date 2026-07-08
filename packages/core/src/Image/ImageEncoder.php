<?php

namespace Pushword\Core\Image;

use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Format;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Pushword\Core\Entity\Media;
use RuntimeException;

final readonly class ImageEncoder
{
    public function encodeOriginal(ImageInterface $image, string $outputPath, int $quality, Media|string $media): void
    {
        $encoded = $this->isSourceWebp($media)
            ? $image->encodeUsingFormat(Format::WEBP, quality: $quality)
            : $image->encode(new AutoEncoder(quality: $quality));

        $this->saveAtomically($encoded, $outputPath);
    }

    public function encodeWebp(ImageInterface $image, string $outputPath, int $quality): void
    {
        $this->saveAtomically($image->encodeUsingFormat(Format::WEBP, quality: $quality), $outputPath);
    }

    /**
     * Write to a unique temp file then atomically rename into place, so a
     * concurrent reader (e.g. the static generator copying the image cache while
     * another process regenerates the same variant) never sees a partial file.
     *
     * A transient encoder failure can yield an empty payload; promoting it would
     * poison the cache with a 0-byte file that reads as fresh forever (broken
     * <img>, never regenerated). Refuse it so the source stays the fallback.
     */
    private function saveAtomically(EncodedImageInterface $encoded, string $outputPath): void
    {
        if ('' === $encoded->toString()) {
            throw new RuntimeException('Refusing to write an empty encoded image to '.$outputPath);
        }

        $tmpPath = $outputPath.'.'.getmypid().'.'.uniqid().'.tmp';
        $encoded->save($tmpPath);
        rename($tmpPath, $outputPath);
    }

    private function isSourceWebp(Media|string $media): bool
    {
        if ($media instanceof Media) {
            return 'image/webp' === $media->getMimeType();
        }

        return 'webp' === strtolower(pathinfo($media, \PATHINFO_EXTENSION));
    }
}
