<?php

namespace Pushword\Core\Service;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use RuntimeException;

/**
 * Stores generated media derivatives while keeping image processing on local files.
 *
 * The default storage points at public/media, so publishing is a no-op. Remote
 * storages receive each complete local derivative only after its atomic local write.
 */
final readonly class MediaCacheStorageAdapter
{
    public function __construct(
        private FilesystemOperator $storage,
        private bool $isLocal = true,
        private string $publicUrl = '',
    ) {
    }

    public function isLocal(): bool
    {
        return $this->isLocal;
    }

    public function getPublicUrl(string $path): ?string
    {
        if ('' === $this->publicUrl) {
            return null;
        }

        return rtrim($this->publicUrl, '/').'/'.ltrim($path, '/');
    }

    public function publish(string $path, string $localPath): void
    {
        if ($this->isLocal) {
            return;
        }

        $stream = @fopen($localPath, 'r');
        if (false === $stream) {
            throw new RuntimeException('Cannot open generated media cache file `'.$localPath.'`.');
        }

        try {
            $this->storage->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    public function fileExists(string $path): bool
    {
        return $this->storage->fileExists($path);
    }

    public function fileSize(string $path): int
    {
        return $this->storage->fileSize($path);
    }

    public function lastModified(string $path): int
    {
        return $this->storage->lastModified($path);
    }

    public function mimeType(string $path): string
    {
        return $this->storage->mimeType($path);
    }

    /** @return resource */
    public function readStream(string $path): mixed
    {
        return $this->storage->readStream($path);
    }

    public function delete(string $path): void
    {
        if ($this->storage->fileExists($path)) {
            $this->storage->delete($path);
        }
    }

    /** @return iterable<int, StorageAttributes> */
    public function listContents(string $path = '', bool $deep = false): iterable
    {
        return $this->storage->listContents($path, $deep);
    }
}
