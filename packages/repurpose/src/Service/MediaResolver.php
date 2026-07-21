<?php

namespace Pushword\Repurpose\Service;

/**
 * Locates a page image's cached derivatives and its source dimensions on disk,
 * reusing core's existing image cache (`{publicDir}/{publicMediaDir}/{filter}/…`).
 * The renderer embeds the smallest derivative that covers the slide, so we prefer
 * the modern `.webp` sibling and fall back through the pyramid.
 */
final readonly class MediaResolver
{
    public function __construct(
        private string $publicDir,
        private string $publicMediaDir,
    ) {
    }

    /**
     * @return array{0: int, 1: int}|null [width, height] of the source, or null if unknown
     */
    public function sourceDimensions(string $filename): ?array
    {
        foreach (['default', 'xl', 'lg'] as $filter) {
            $path = $this->rawPath($filename, $filter);
            if (null !== $path) {
                $size = @getimagesize($path);
                if (false !== $size) {
                    return [$size[0], $size[1]];
                }
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
        return $this->rawPath($filename, $filter) ?? $this->rawPath($filename, 'default');
    }

    private function rawPath(string $filename, string $filter): ?string
    {
        $base = $this->publicDir.'/'.$this->publicMediaDir.'/'.$filter.'/';
        $webp = $base.$this->withExtension($filename, 'webp');
        if (is_file($webp)) {
            return $webp;
        }

        $original = $base.$filename;

        return is_file($original) ? $original : null;
    }

    private function withExtension(string $filename, string $extension): string
    {
        $dot = strrpos($filename, '.');

        return (false === $dot ? $filename : substr($filename, 0, $dot)).'.'.$extension;
    }
}
