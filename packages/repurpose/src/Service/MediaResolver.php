<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Repository\MediaRepository;

/**
 * Locates a page image's cached derivatives and its source dimensions on disk,
 * reusing core's existing image cache (`{mediaCacheDir}/{filter}/…`).
 * The renderer embeds the smallest derivative that covers the slide, so we prefer
 * the modern `.webp` sibling and fall back through the pyramid.
 */
final readonly class MediaResolver
{
    public function __construct(
        private string $mediaCacheDir,
        private MediaRepository $mediaRepository,
        private ImageCacheGenerator $imageCacheGenerator,
    ) {
    }

    /**
     * @return array{0: int, 1: int}|null [width, height] of the source, or null if unknown
     */
    public function sourceDimensions(string $filename): ?array
    {
        $this->ensureDefaultDerivative($filename);
        foreach (['default', 'xl', 'lg'] as $filter) {
            $path = $this->rawPath($filename, $filter);
            if (null === $path) {
                continue;
            }

            $size = @getimagesize($path);
            if (false !== $size) {
                return [$size[0], $size[1]];
            }
        }

        return null;
    }

    /**
     * Absolute path of the derivative to embed, preferring `.webp`, then the
     * original extension, then the `default` derivative. Null if nothing is cached.
     */
    public function derivativePath(string $filename, string $filter): ?string
    {
        $this->ensureDefaultDerivative($filename);

        return $this->rawPath($filename, $filter) ?? $this->rawPath($filename, 'default');
    }

    /**
     * Core builds a new image's derivatives in a background `pw:image:cache`, so a
     * slide rendered right after the upload would show the grey placeholder. Build
     * the `default` one now, as core's media-cache route does for a browser — unless
     * decoding the source would not fit in memory.
     */
    private function ensureDefaultDerivative(string $filename): void
    {
        if (null !== $this->rawPath($filename, 'default')) {
            return;
        }

        $media = $this->mediaRepository->findOneByFileName($filename);
        if (! $media instanceof Media || ! $media->isImage()) {
            return;
        }

        if (! $this->imageCacheGenerator->decodeFitsInMemory($media)) {
            return;
        }

        try {
            $this->imageCacheGenerator->generateFilteredCache($media, 'default');
        } catch (Exception) {
            // ImageReader's "cannot read image": the placeholder stays, as for a missing file.
        }
    }

    private function rawPath(string $filename, string $filter): ?string
    {
        $base = $this->mediaCacheDir.'/'.$filter.'/';
        foreach ([$base.$this->withExtension($filename, 'webp'), $base.$filename] as $path) {
            // A 0-byte derivative is an optimizer killed mid-write: treat it as missing.
            if (is_file($path) && filesize($path) > 0) {
                return $path;
            }
        }

        return null;
    }

    private function withExtension(string $filename, string $extension): string
    {
        $dot = strrpos($filename, '.');

        return (false === $dot ? $filename : substr($filename, 0, $dot)).'.'.$extension;
    }
}
