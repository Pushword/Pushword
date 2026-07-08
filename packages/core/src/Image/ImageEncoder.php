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
    /**
     * Intervention/GD/Imagick intermittently returns an empty payload for a
     * large resize (memory pressure, driver hiccup) — a transient failure a
     * fresh encode recovers from. Retry before giving up so one hiccup does not
     * leave a variant missing until the next scheduled run.
     */
    private const int ENCODE_ATTEMPTS = 3;

    public function encodeOriginal(ImageInterface $image, string $outputPath, int $quality, Media|string $media): void
    {
        $this->saveAtomically(fn (): EncodedImageInterface => $this->encodeSource($image, $quality, $media), $outputPath);
    }

    /**
     * Encode an image in its source format and return the raw bytes.
     *
     * Used when rewriting the master file (e.g. rotation) through the storage
     * adapter, which needs a string rather than a local path. Rejects an empty
     * payload for the same reason saveAtomically() does — never overwrite a
     * master with a 0-byte file.
     */
    public function encodeOriginalToString(ImageInterface $image, int $quality, Media|string $media): string
    {
        return $this->encodeNonEmpty(fn (): EncodedImageInterface => $this->encodeSource($image, $quality, $media));
    }

    public function encodeWebp(ImageInterface $image, string $outputPath, int $quality): void
    {
        $this->saveAtomically(static fn (): EncodedImageInterface => $image->encodeUsingFormat(Format::WEBP, quality: $quality), $outputPath);
    }

    private function encodeSource(ImageInterface $image, int $quality, Media|string $media): EncodedImageInterface
    {
        return $this->isSourceWebp($media)
            ? $image->encodeUsingFormat(Format::WEBP, quality: $quality)
            : $image->encode(new AutoEncoder(quality: $quality));
    }

    /**
     * Encode with a bounded retry, returning the raw bytes and never an empty
     * string. A transient failure yields an empty payload that would blank a
     * master or poison a cache variant; retry recovers it, then we throw rather
     * than hand back "" so the caller reports an error instead of success.
     *
     * @param callable(): EncodedImageInterface $encode
     */
    private function encodeNonEmpty(callable $encode): string
    {
        for ($attempt = 1; $attempt <= self::ENCODE_ATTEMPTS; ++$attempt) {
            $bytes = $encode()->toString();
            if ('' !== $bytes) {
                return $bytes;
            }
        }

        throw new RuntimeException('Image encoder produced an empty payload after '.self::ENCODE_ATTEMPTS.' attempts.');
    }

    /**
     * Write to a unique temp file then atomically rename into place, so a
     * concurrent reader (e.g. the static generator copying the image cache while
     * another process regenerates the same variant) never sees a partial file.
     *
     * The bytes must reach disk intact before committing: an empty encode or a
     * truncated write (disk full, IO error) would otherwise be renamed over a
     * valid variant, poisoning the cache with a 0-byte file that reads as fresh
     * forever (broken <img>, never regenerated).
     *
     * @param callable(): EncodedImageInterface $encode
     */
    private function saveAtomically(callable $encode, string $outputPath): void
    {
        $bytes = $this->encodeNonEmpty($encode);

        $tmpPath = $outputPath.'.'.getmypid().'.'.uniqid().'.tmp';
        $written = @file_put_contents($tmpPath, $bytes);

        if (\strlen($bytes) !== $written) {
            @unlink($tmpPath);

            throw new RuntimeException(\sprintf('Encoded image write to %s was truncated (%s of %d bytes).', $outputPath, false === $written ? 'failed' : (string) $written, \strlen($bytes)));
        }

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
