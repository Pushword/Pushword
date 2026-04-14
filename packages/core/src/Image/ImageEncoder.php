<?php

namespace Pushword\Core\Image;

use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Format;
use Intervention\Image\Interfaces\ImageInterface;
use Pushword\Core\Entity\Media;

final readonly class ImageEncoder
{
    public function encodeOriginal(ImageInterface $image, string $outputPath, int $quality, Media|string $media): void
    {
        if ($this->isSourceWebp($media)) {
            $image->encodeUsingFormat(Format::WEBP, quality: $quality)->save($outputPath);
        } else {
            $image->encode(new AutoEncoder(quality: $quality))->save($outputPath);
        }
    }

    public function encodeWebp(ImageInterface $image, string $outputPath, int $quality): void
    {
        $image->encodeUsingFormat(Format::WEBP, quality: $quality)->save($outputPath);
    }

    private function isSourceWebp(Media|string $media): bool
    {
        if ($media instanceof Media) {
            return 'image/webp' === $media->getMimeType();
        }

        return 'webp' === strtolower(pathinfo($media, \PATHINFO_EXTENSION));
    }
}
