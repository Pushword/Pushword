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

    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->copyMediaToDownload();
    }

    /**
     * Copy or Symlink "not image" media to download folder.
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

        // Use MediaStorageAdapter if available, otherwise fall back to direct filesystem
        if (null !== $this->mediaStorage) {
            $this->copyFromStorage($staticMediaDir, $symlink, $mediaDir);

            return;
        }

        // Fallback for local-only (backward compatibility)
        $dir = dir($mediaDir);
        if (false === $dir) {
            return;
        }

        while (false !== $entry = $dir->read()) {
            if ('.' === $entry) {
                continue;
            }

            if ('..' === $entry) {
                continue;
            }

            if ($symlink) {
                $this->filesystem->symlink($mediaDir.'/'.$entry, $staticMediaDir.'/'.$entry);

                continue;
            }

            $this->filesystem->copy($mediaDir.'/'.$entry, $staticMediaDir.'/'.$entry);
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
                $this->filesystem->symlink($mediaDir.'/'.$fileName, $staticMediaDir.'/'.$fileName);
            }

            return;
        }

        // Copy from storage to static directory
        foreach ($this->mediaStorage->listContents('') as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $fileName = $item->path();
            $targetPath = $staticMediaDir.'/'.$fileName;

            // Read from storage and write to local static dir
            $stream = $this->mediaStorage->readStream($fileName);
            file_put_contents($targetPath, $stream);
            fclose($stream);
        }
    }
}
