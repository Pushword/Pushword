<?php

namespace Pushword\Core\Image;

use Pushword\Core\Entity\Media;
use Spatie\ImageOptimizer\OptimizerChain;
use Throwable;

final readonly class ImageOptimizer
{
    public function __construct(
        private ImageCacheManager $imageCacheManager,
        private ImageCacheGenerator $imageCacheGenerator,
        private OptimizerChain $optimizer,
    ) {
    }

    public function optimize(Media $media): void
    {
        foreach (array_keys($this->imageCacheManager->getFilterSets()) as $filterName) {
            $this->optimizeFilter($media, $filterName);
        }
    }

    public function optimizeFilter(Media $media, string $filterName): void
    {
        /** @var string[] $formats */
        $formats = $this->imageCacheManager->getFilterSets()[$filterName]['formats'] ?? ['original', 'webp'];

        $extensions = [];
        foreach ($formats as $format) {
            $extensions[] = 'original' === $format ? null : $format;
        }

        // Regenerate when a variant is missing OR poisoned (0-byte): a previously
        // truncated derivative must be rebuilt before we try to optimize it.
        foreach ($extensions as $extension) {
            if (! $this->isUsable($this->imageCacheManager->getFilterPath($media, $filterName, $extension))) {
                $this->imageCacheGenerator->generateFilteredCache($media, $filterName);

                break;
            }
        }

        foreach ($extensions as $extension) {
            $path = $this->imageCacheManager->getFilterPath($media, $filterName, $extension);
            if ($this->isUsable($path)) {
                $this->optimizeAtomically($path);
            }
        }
    }

    /**
     * Optimize a variant into a throwaway copy, then atomically swap it into place
     * only when the result is a complete, decodable image.
     *
     * The Spatie optimizers shell out to external binaries (cwebp, mozjpeg, …) that
     * rewrite their target in place and truncate it at open. A process killed
     * mid-encode — OOM or the per-optimizer timeout, worst on large variants under
     * parallel batch load plus on-demand traffic — would otherwise leave the live
     * derivative at 0 bytes, served as a broken image forever. Isolating the write
     * keeps the valid file untouched until a good result is ready.
     */
    private function optimizeAtomically(string $path): void
    {
        $tmpPath = $path.'.opt-'.getmypid().'.'.uniqid().'.tmp';

        try {
            $this->optimizer->optimize($path, $tmpPath);

            if ($this->isUsable($tmpPath) && false !== @getimagesize($tmpPath)) {
                rename($tmpPath, $path);
            }
        } catch (Throwable) {
            // Optimizer failed (binary killed/timed out): keep the valid, unoptimized derivative.
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * A variant is usable only when it exists AND is non-empty; a 0-byte file
     * (a truncated encode) counts as missing so it is rebuilt, never optimized.
     */
    private function isUsable(string $path): bool
    {
        return is_file($path) && 0 < (@filesize($path) ?: 0);
    }
}
