<?php

namespace Pushword\StaticGenerator\Generator;

use Override;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\StaticGenerator\IncrementalGeneratorInterface;

class MediaGenerator extends AbstractGenerator implements IncrementalGeneratorInterface
{
    private ?MediaStorageAdapter $mediaStorage = null;

    private bool $incremental = false;

    public function setMediaStorage(MediaStorageAdapter $mediaStorage): void
    {
        $this->mediaStorage = $mediaStorage;
    }

    public function setIncremental(bool $incremental): void
    {
        $this->incremental = $incremental;
    }

    private function targetExists(string $targetPath): bool
    {
        // Check for broken symlinks: is_link() returns true but file_exists() returns false
        if (is_link($targetPath) && ! $this->filesystem->exists($targetPath)) {
            // Remove broken symlink so we can replace it with a real file
            $this->filesystem->remove($targetPath);

            return false;
        }

        return is_link($targetPath) || $this->filesystem->exists($targetPath);
    }

    /**
     * Check if source file is newer than target file.
     */
    private function isSourceNewer(string $sourcePath, string $targetPath): bool
    {
        if (! $this->filesystem->exists($targetPath) && ! is_link($targetPath)) {
            return true;
        }

        $sourceMtime = filemtime($sourcePath);
        $targetMtime = filemtime($targetPath);

        if (false === $sourceMtime || false === $targetMtime) {
            return true;
        }

        return $sourceMtime > $targetMtime;
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

        if (! $this->filesystem->exists($staticMediaDir)) {
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
        if ($this->filesystem->exists($publicMediaCacheDir)) {
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

            // Handle directories (image cache formats like md/, thumb/, xs/, etc.)
            if (is_dir($sourcePath) && ! is_link($sourcePath)) {
                if ($this->targetExists($targetPath)) {
                    // In incremental mode, update existing directories
                    if ($this->incremental && ! $symlink) {
                        $this->copyImageCacheIncremental($sourcePath, $targetPath);
                    }

                    continue;
                }

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
                if (! $this->filesystem->exists($sourcePath)) {
                    continue;
                }

                if ($this->targetExists($targetPath)) {
                    continue;
                }

                if ($symlink) {
                    // Recreate the symlink with same target
                    $linkTarget = readlink($sourcePath);
                    if (false !== $linkTarget) {
                        $this->filesystem->symlink($linkTarget, $targetPath);
                    }
                } else {
                    // Copy the actual file content (resolve symlink)
                    $this->filesystem->copy($sourcePath, $targetPath);
                }
            }
        }
    }

    /**
     * Incrementally update image cache directory by copying only modified files.
     */
    private function copyImageCacheIncremental(string $sourceDir, string $targetDir): void
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

            if (is_dir($sourcePath) && ! is_link($sourcePath)) {
                if (! $this->filesystem->exists($targetPath)) {
                    $this->filesystem->mkdir($targetPath);
                }

                $this->copyImageCacheIncremental($sourcePath, $targetPath);

                continue;
            }

            if (is_file($sourcePath) && $this->isSourceNewer($sourcePath, $targetPath)) {
                $this->filesystem->copy($sourcePath, $targetPath, true);
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

            $sourcePath = $sourceDir.'/'.$entry;
            $targetPath = $targetDir.'/'.$entry;

            // In incremental mode, check modification time
            if ($this->incremental && $this->targetExists($targetPath)) {
                if (! $symlink && $this->isSourceNewer($sourcePath, $targetPath)) {
                    $this->filesystem->copy($sourcePath, $targetPath, true);
                }

                continue;
            }

            if ($this->targetExists($targetPath)) {
                continue;
            }

            if ($symlink) {
                $this->filesystem->symlink($sourcePath, $targetPath);

                continue;
            }

            $this->filesystem->copy($sourcePath, $targetPath);
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
                $this->filesystem->dumpFile($target, $stream);
                fclose($stream);
            }
        }
    }
}
