<?php

namespace Pushword\Repurpose\Service;

use RuntimeException;

/**
 * Persists a carousel's pin image (its cover slide, rasterised to PNG) to a
 * public folder under the web root, so an external service — Pinterest's
 * "create pin" widget — can fetch it by URL. Pinterest pulls the `media` server
 * side and only accepts a raster (jpg/png/gif), which the self-contained slide
 * SVGs are not, hence this public PNG.
 */
final readonly class PinImageStore
{
    private const string SUBDIR = 'repurpose-pin';

    public function __construct(
        private string $publicDir,
    ) {
    }

    /**
     * The pin image's path from the web root (e.g. `/repurpose-pin/12.png`) — join
     * it to the request's scheme and host for the absolute URL Pinterest fetches.
     */
    public function publicPath(int $id): string
    {
        return '/'.self::SUBDIR.'/'.$id.'.png';
    }

    public function absolutePath(int $id): string
    {
        return $this->publicDir.'/'.self::SUBDIR.'/'.$id.'.png';
    }

    public function exists(int $id): bool
    {
        return is_file($this->absolutePath($id));
    }

    /**
     * Write the PNG bytes and return the public URL path they are served at.
     */
    public function save(int $id, string $png): string
    {
        $dir = $this->publicDir.'/'.self::SUBDIR;
        if (! is_dir($dir) && ! @mkdir($dir, 0o775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create the pin-image directory.');
        }

        if (false === file_put_contents($this->absolutePath($id), $png)) {
            throw new RuntimeException('Cannot write the pin image.');
        }

        return $this->publicPath($id);
    }
}
