<?php

namespace Pushword\StaticGenerator\Generator;

use Override;
use Pushword\Core\Service\MediaStorageAdapter;

class MediaGenerator extends AbstractGenerator
{
    private ?MediaStorageAdapter $mediaStorage = null;

    public function setMediaStorage(MediaStorageAdapter $mediaStorage): void
    {
        $this->mediaStorage = $mediaStorage;
    }

    private function targetExists(string $targetPath): bool
    {
        return is_link($targetPath) || file_exists($targetPath);
    }

    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->copyMediaToDownload();
    }

    /**
     * Copy or Symlink media files to static folder.
     * This includes both original files and the image cache (thumbnails, webp versions).
     */
    protected function copyMediaToDownload(): void
    {
        $publicMediaDir = $this->params->get('pw.public_media_dir');
        $mediaDir = $this->params->get('pw.media_dir');
        $staticMediaDir = $this->getStaticDir().'/'.$publicMediaDir;

        $symlink = $this->mustSymlink();

        // fix when media symlink exist and then, we want to copy
        if (is_link($staticMediaDir)) {
            return;
        }

        if (! file_exists($staticMediaDir)) {
            $this->filesystem->mkdir($staticMediaDir);
        }

        // Copy original media files from storage
        if (null !== $this->mediaStorage) {
            $this->copyFromStorage($staticMediaDir, $symlink, $mediaDir);
        } else {
            // Fallback for local-only (backward compatibility)
            $this->copyDirectoryContents($mediaDir, $staticMediaDir, $symlink);
        }

        // Copy image cache from public/media (thumbnails, webp versions, etc.)
        $publicMediaCacheDir = $this->publicDir.'/'.$publicMediaDir;
        if (file_exists($publicMediaCacheDir)) {
            $this->copyImageCache($publicMediaCacheDir, $staticMediaDir, $symlink);
        }
    }

    /**
     * Copy image cache directories (md/, thumb/, xs/, etc.) and symlinked files to static folder.
     */
    private function copyImageCache(string $sourceDir, string $targetDir, bool $symlink): void
    {
        $dir = dir($sourceDir);
        if (false === $dir) {
            return;
        }

        while (false !== $entry = $dir->read()) {
            if (\in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $sourcePath = $sourceDir.'/'.$entry;
            $targetPath = $targetDir.'/'.$entry;

            if ($this->targetExists($targetPath)) {
                continue;
            }

            // Handle directories (image cache formats like md/, thumb/, xs/, etc.)
            if (is_dir($sourcePath) && ! is_link($sourcePath)) {
                if ($symlink) {
                    $this->filesystem->symlink($sourcePath, $targetPath);
                } else {
                    $this->filesystem->mirror($sourcePath, $targetPath);
                }

                continue;
            }

            // Handle symlinks to original files (public/media/1.jpg -> ../../media/1.jpg)
            if (is_link($sourcePath)) {
                // Skip broken symlinks
                if (! file_exists($sourcePath)) {
                    continue;
                }

                if ($symlink) {
                    // Recreate the symlink with same target
                    $linkTarget = readlink($sourcePath);
                    if (false !== $linkTarget) {
                        @symlink($linkTarget, $targetPath);
                    }
                } else {
                    // Copy the actual file content (resolve symlink)
                    $this->filesystem->copy($sourcePath, $targetPath);
                }
            }
        }
    }

    /**
     * Copy directory contents (fallback for local-only).
     */
    private function copyDirectoryContents(string $sourceDir, string $targetDir, bool $symlink): void
    {
        $dir = dir($sourceDir);
        if (false === $dir) {
            return;
        }

        while (false !== $entry = $dir->read()) {
            if (\in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $targetPath = $targetDir.'/'.$entry;

            if ($this->targetExists($targetPath)) {
                continue;
            }

            if ($symlink) {
                $this->filesystem->symlink($sourceDir.'/'.$entry, $targetPath);

                continue;
            }

            $this->filesystem->copy($sourceDir.'/'.$entry, $targetPath);
        }
    }

    private function copyFromStorage(string $staticMediaDir, bool $symlink, string $mediaDir): void
    {
        if (null === $this->mediaStorage) {
            return;
        }

        // For local storage with symlink, we can still symlink
        if ($symlink && $this->mediaStorage->isLocal()) {
            foreach ($this->mediaStorage->listContents('') as $item) {
                if (! $item->isFile()) {
                    continue;
                }

                $fileName = $item->path();
                $targetPath = $staticMediaDir.'/'.$fileName;

                if ($this->targetExists($targetPath)) {
                    continue;
                }

                $this->filesystem->symlink($mediaDir.'/'.$fileName, $targetPath);
            }

            return;
        }

        // Copy from storage to static directory
        $this->copyFilesFromRemoteStorage($staticMediaDir);
    }

    private function copyFilesFromRemoteStorage(string $staticMediaDir): void
    {
        if (null === $this->mediaStorage) {
            return;
        }

        // Collect files to copy first
        $filesToCopy = [];
        foreach ($this->mediaStorage->listContents('') as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $fileName = $item->path();
            $targetPath = $staticMediaDir.'/'.$fileName;

            if ($this->targetExists($targetPath)) {
                continue;
            }

            $filesToCopy[$fileName] = $targetPath;
        }

        // Copy files in batches
        $this->copyFilesInBatches($filesToCopy);
    }

    private const int BATCH_SIZE = 20;

    /**
     * @param array<string, string> $filesToCopy [sourcePath => targetPath]
     */
    private function copyFilesInBatches(array $filesToCopy): void
    {
        if (null === $this->mediaStorage) {
            return;
        }

        $batches = array_chunk($filesToCopy, self::BATCH_SIZE, true);
        foreach ($batches as $batch) {
            // Pre-fetch all streams
            $streams = [];
            foreach ($batch as $source => $target) {
                $streams[$target] = $this->mediaStorage->readStream($source);
            }

            // Write all files
            foreach ($streams as $target => $stream) {
                file_put_contents($target, $stream);
                fclose($stream);
            }
        }
    }
}
