<?php

namespace Pushword\Core\Image;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Deletes the scratch files no writer will ever clean up.
 *
 * Both writers unlink their own file — ImageOptimizer in a `finally`, ImageEncoder
 * on the truncated-write path — but neither runs when the process is killed
 * outright: an OOM, or a deploy restart taking the caller's cgroup with it
 * ({@see \Pushword\Core\BackgroundTask\ProcessBackgroundTaskDispatcher}). What is
 * left then stays forever, because nothing else walks these trees to remove it: a
 * production media tree was found holding 255 of them, 17.9 MB, the oldest six
 * weeks old.
 */
final readonly class ImageScratchSweeper
{
    public function __construct(
        private string $mediaDir,
        private string $mediaCacheDir,
    ) {
    }

    /**
     * Walks where the writers actually write — the masters and the derivatives —
     * read from the container, never a fixed path: a sweep aimed at a tree the
     * writers left behind would report a reassuring 0 forever.
     *
     * @return array{swept: int, empty: int}
     */
    public function sweep(): array
    {
        $deadline = time() - ImageScratchFile::MAX_AGE;
        $swept = 0;
        $empty = 0;

        foreach ($this->resolvedDirectories() as $dir) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! $file->isFile() || ! ImageScratchFile::isScratch($file->getFilename())) {
                    continue;
                }

                if ($file->getMTime() > $deadline) {
                    continue;
                }

                // Read before the unlink: an empty scratch is the fingerprint of an
                // encoder returning a blank payload, and the only trace that the
                // promotion guard caught one. Sweeping silently would erase the
                // evidence that the guard is doing its job.
                $isEmpty = 0 === $file->getSize();

                if (! @unlink($file->getPathname())) {
                    continue;
                }

                ++$swept;
                if ($isEmpty) {
                    ++$empty;
                }
            }
        }

        return ['swept' => $swept, 'empty' => $empty];
    }

    /**
     * realpath() so a site pointing both settings at one directory walks it once,
     * and so a directory it has not created yet drops out rather than throwing.
     *
     * @return string[]
     */
    private function resolvedDirectories(): array
    {
        $dirs = [];
        foreach ([$this->mediaDir, $this->mediaCacheDir] as $configured) {
            $real = realpath($configured);
            if (false !== $real && ! \in_array($real, $dirs, true)) {
                $dirs[] = $real;
            }
        }

        return $dirs;
    }
}
