<?php

namespace Pushword\Core\Tests\Image;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Image\ExternalImageImporter;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class ExternalImageImporterTest extends KernelTestCase
{
    use PathTrait;

    private function createImporter(): ExternalImageImporter
    {
        self::bootKernel();

        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $imageReader = new ImageReader($mediaStorage);
        $imageEncoder = new ImageEncoder();
        $imageCacheManager = new ImageCacheManager([], $this->publicDir, $this->publicMediaDir, $mediaStorage);

        $backgroundTaskDispatcher = self::getContainer()->get(BackgroundTaskDispatcherInterface::class);
        $imageCacheGenerator = new ImageCacheGenerator($imageReader, $imageEncoder, $imageCacheManager, $backgroundTaskDispatcher, $mediaStorage);

        return new ExternalImageImporter($mediaStorage, $imageCacheGenerator, $this->getMediaDir(), $this->projectDir);
    }

    public function testImportExternal(): void
    {
        $importer = $this->createImporter();

        $url = 'https://piedweb.com/assets/pw/favicon-32x32.png';
        // Pre-seed the importer's local cache from a fixture so the test never hits
        // the network (the live fetch was a recurring source of CI flakiness). The
        // cache key derives from the URL, so the expected filenames stay unchanged.
        new Filesystem()->copy(__DIR__.'/fixtures/favicon-32x32.png', sys_get_temp_dir().'/'.sha1($url), true);

        $media = $importer->importExternal($url, 'favicon', 'favicon');
        self::assertSame('favicon', $media->getAlt());
        self::assertSame('favicon-dd93.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());

        $media = $importer->importExternal($url, 'favicon', 'favicon', false);
        self::assertSame('favicon.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());

        $media = $importer->importExternal($url, 'favicon from pied web');
        self::assertSame('favicon-from-pied-web-dd93.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());

        // Cleanup imported files
        @unlink($this->getMediaDir().'/favicon-dd93.png');
        @unlink($this->getMediaDir().'/favicon.png');
        @unlink($this->getMediaDir().'/favicon-from-pied-web-dd93.png');
    }
}
