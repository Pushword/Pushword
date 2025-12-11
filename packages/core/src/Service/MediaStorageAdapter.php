<?php

namespace Pushword\Core\Service;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

use function Safe\file_put_contents;

/**
 * Adapter service wrapping Flysystem for media storage operations.
 * Provides a getLocalPath() method for libraries requiring filesystem paths
 * (like Intervention Image or Spatie Image Optimizer).
 */
final class MediaStorageAdapter
{
    public function __construct(
        private readonly FilesystemOperator $storage,
        private readonly string $mediaDir,
        private readonly bool $isLocal = true,
    ) {
    }

    /**
     * Read file contents as a string.
     *
     * @throws FilesystemException|UnableToReadFile
     */
    public function read(string $path): string
    {
        return $this->storage->read($path);
    }

    /**
     * Read file contents as a stream.
     *
     * @return resource
     *
     * @throws FilesystemException|UnableToReadFile
     */
    public function readStream(string $path)
    {
        return $this->storage->readStream($path);
    }

    /**
     * Write contents to a file.
     *
     * @throws FilesystemException|UnableToWriteFile
     */
    public function write(string $path, string $contents): void
    {
        $this->storage->write($path, $contents);
    }

    /**
     * Write a stream to a file.
     *
     * @param resource $stream
     *
     * @throws FilesystemException|UnableToWriteFile
     */
    public function writeStream(string $path, $stream): void
    {
        $this->storage->writeStream($path, $stream);
    }

    /**
     * Delete a file.
     *
     * @throws FilesystemException|UnableToDeleteFile
     */
    public function delete(string $path): void
    {
        $this->storage->delete($path);
    }

    /**
     * Move (rename) a file.
     *
     * @throws FilesystemException|UnableToMoveFile
     */
    public function move(string $source, string $destination): void
    {
        $this->storage->move($source, $destination);
    }

    /**
     * Copy a file.
     *
     * @throws FilesystemException
     */
    public function copy(string $source, string $destination): void
    {
        $this->storage->copy($source, $destination);
    }

    /**
     * Check if a file exists.
     *
     * @throws FilesystemException
     */
    public function fileExists(string $path): bool
    {
        return $this->storage->fileExists($path);
    }

    /**
     * Get file size in bytes.
     *
     * @throws FilesystemException
     */
    public function fileSize(string $path): int
    {
        return $this->storage->fileSize($path);
    }

    /**
     * Get file MIME type.
     *
     * @throws FilesystemException
     */
    public function mimeType(string $path): string
    {
        return $this->storage->mimeType($path);
    }

    /**
     * Get file last modified timestamp.
     *
     * @throws FilesystemException
     */
    public function lastModified(string $path): int
    {
        return $this->storage->lastModified($path);
    }

    /**
     * List contents of a directory.
     *
     * @return iterable<\League\Flysystem\StorageAttributes>
     *
     * @throws FilesystemException
     */
    public function listContents(string $path = '', bool $deep = false): iterable
    {
        return $this->storage->listContents($path, $deep);
    }

    /**
     * Get the underlying Flysystem filesystem operator.
     */
    public function getStorage(): FilesystemOperator
    {
        return $this->storage;
    }

    /**
     * Get a local filesystem path for the given file.
     *
     * For local storage: returns the direct path.
     * For remote storage: downloads to a temp file and returns that path.
     *
     * This is needed for libraries like Intervention Image that require
     * actual filesystem paths to read files.
     */
    public function getLocalPath(string $path): string
    {
        if ($this->isLocal) {
            return $this->mediaDir.'/'.$path;
        }

        // Download to temp for remote storage
        $tempPath = sys_get_temp_dir().'/'.sha1($path).'_'.basename($path);

        if (! file_exists($tempPath)) {
            file_put_contents($tempPath, $this->storage->read($path));
        }

        return $tempPath;
    }

    /**
     * Check if storage is local.
     */
    public function isLocal(): bool
    {
        return $this->isLocal;
    }

    /**
     * Get the base media directory path (for local storage).
     */
    public function getMediaDir(): string
    {
        return $this->mediaDir;
    }
}
