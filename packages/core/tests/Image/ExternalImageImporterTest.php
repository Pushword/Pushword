<?php

namespace Pushword\Core\Tests\Image;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Image\ExternalImageImporter;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class ExternalImageImporterTest extends KernelTestCase
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
        $thumbnailGenerator = new ThumbnailGenerator($imageReader, $imageEncoder, $imageCacheManager, $backgroundTaskDispatcher, $mediaStorage);

        return new ExternalImageImporter($mediaStorage, $thumbnailGenerator, $this->getMediaDir(), $this->projectDir);
    }

    public function testImportExternal(): void
    {
        $importer = $this->createImporter();

        $media = $importer->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon', 'favicon');
        self::assertSame('favicon', $media->getAlt());
        self::assertSame('favicon-dd93.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());

        $media = $importer->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon', 'favicon', false);
        self::assertSame('favicon.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());

        $media = $importer->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon from pied web');
        self::assertSame('favicon-from-pied-web-dd93.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());

        // Cleanup imported files
        @unlink($this->getMediaDir().'/favicon-dd93.png');
        @unlink($this->getMediaDir().'/favicon.png');
        @unlink($this->getMediaDir().'/favicon-from-pied-web-dd93.png');
    }
}
