<?php

declare(strict_types=1);

namespace Pushword\Core\Image;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Local derivative budget, ordered by generation time: static hits bypass PHP,
 * so this deliberately does not pretend to track the last access.
 */
final readonly class ImageCachePolicy
{
    /** @param array<string, array<string, mixed>> $filterSets */
    public function __construct(
        private bool $imageCacheOnDemand,
        private int $imageCacheMaxSize,
        private string $mediaCacheDir,
        private array $filterSets,
        private LockFactory $lockFactory,
    ) {
    }

    public function isOnDemand(): bool
    {
        return $this->imageCacheOnDemand;
    }

    public function createVariantLock(string $path): LockInterface
    {
        return $this->lockFactory->createLock('pw:image:variant:'.sha1($path));
    }

    public function prune(): void
    {
        if (! $this->imageCacheOnDemand || 0 === $this->imageCacheMaxSize) {
            return;
        }

        $lock = $this->lockFactory->createLock('pw:image:prune:'.sha1($this->mediaCacheDir));
        $lock->acquire(blocking: true);

        try {
            $files = [];
            $total = 0;
            foreach (array_keys($this->filterSets) as $filter) {
                $directory = $this->mediaCacheDir.'/'.$filter;
                if (! is_dir($directory) || is_link($directory)) {
                    continue;
                }

                foreach (new Finder()->files()->depth(0)->in($directory) as $file) {
                    if ($file->isLink() || ImageScratchFile::isScratch($file->getFilename())) {
                        continue;
                    }

                    $path = $file->getPathname();
                    clearstatcache(true, $path);
                    $size = @filesize($path);
                    $time = @filemtime($path);
                    if (false === $size || false === $time) {
                        continue;
                    }

                    // A variant that cannot fit must not evict the rest of the cache.
                    if ($size > $this->imageCacheMaxSize) {
                        $this->removeVariant($path);

                        continue;
                    }

                    $files[] = ['path' => $path, 'size' => $size, 'time' => $time];
                    $total += $size;
                }
            }

            usort($files, static fn (array $a, array $b): int => ($a['time'] <=> $b['time']) ?: strcmp($a['path'], $b['path']));

            foreach ($files as $file) {
                if ($total <= $this->imageCacheMaxSize) {
                    break;
                }

                if ($this->removeVariant($file['path'])) {
                    $total -= $file['size'];
                }
            }
        } finally {
            $lock->release();
        }
    }

    private function removeVariant(string $path): bool
    {
        $lock = $this->createVariantLock($path);
        if (! $lock->acquire()) {
            return false;
        }

        try {
            return ! is_link($path) && @unlink($path);
        } finally {
            $lock->release();
        }
    }
}
