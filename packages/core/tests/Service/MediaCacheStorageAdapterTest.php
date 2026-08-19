<?php

namespace Pushword\Core\Tests\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\MediaCacheStorageAdapter;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class MediaCacheStorageAdapterTest extends TestCase
{
    public function testPublishesAndDeletesRemoteDerivative(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $cacheDir = sys_get_temp_dir().'/pushword-remote-cache-'.getmypid();
        $localPath = $cacheDir.'/md/photo.webp';
        $filesystem = new SymfonyFilesystem();
        $filesystem->dumpFile($localPath, 'generated-image');

        try {
            $adapter = new MediaCacheStorageAdapter(
                $storage,
                isLocal: false,
                publicUrl: 'https://media.example.test/cache',
            );
            $adapter->publish('md/photo.webp', $localPath);

            self::assertTrue($adapter->fileExists('md/photo.webp'));
            self::assertSame(15, $adapter->fileSize('md/photo.webp'));
            self::assertSame('generated-image', $storage->read('md/photo.webp'));
            self::assertSame('https://media.example.test/cache/md/photo.webp', $adapter->getPublicUrl('md/photo.webp'));

            $adapter->delete('md/photo.webp');
            self::assertFalse($adapter->fileExists('md/photo.webp'));
        } finally {
            $filesystem->remove($cacheDir);
        }
    }

    public function testPublicUrlIsOptional(): void
    {
        $adapter = new MediaCacheStorageAdapter(
            new Filesystem(new InMemoryFilesystemAdapter()),
            isLocal: false,
        );

        self::assertNull($adapter->getPublicUrl('md/photo.webp'));
    }

    public function testPublishingMissingLocalFileFailsClearly(): void
    {
        $adapter = new MediaCacheStorageAdapter(
            new Filesystem(new InMemoryFilesystemAdapter()),
            isLocal: false,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Cannot open generated media cache file');

        $adapter->publish('md/photo.webp', '/definitely/missing/pushword-photo.webp');
    }
}
