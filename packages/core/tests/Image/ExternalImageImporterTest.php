<?php

namespace Pushword\Core\Tests\Image;

use Override;
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

    /**
     * Kept verbatim: the expected filenames embed a hash derived from this URL
     * (substr(md5(sha1($url)), 0, 4) === 'dd93'), so it must not change.
     */
    private const string URL = 'https://piedweb.com/assets/pw/favicon-32x32.png';

    private string $cacheFile = '';

    #[Override]
    protected function setUp(): void
    {
        // ExternalImageImporter::cacheExternalImage() returns sys_temp/sha1($url)
        // untouched when it already exists, only fetching the URL otherwise. Seed
        // that cache from a committed PNG fixture so the importer never reaches the
        // network (the test used to fetch a live URL and failed on offline CI).
        $this->cacheFile = sys_get_temp_dir().'/'.sha1(self::URL);
        new Filesystem()->copy(__DIR__.'/../fixtures/favicon-32x32.png', $this->cacheFile, true);
    }

    protected function tearDown(): void
    {
        // Robust even if an assertion above failed: drop the seeded cache and the
        // three media files the import produced.
        new Filesystem()->remove([
            $this->cacheFile,
            $this->getMediaDir().'/favicon-dd93.png',
            $this->getMediaDir().'/favicon.png',
            $this->getMediaDir().'/favicon-from-pied-web-dd93.png',
        ]);
        parent::tearDown();
    }

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

        $media = $importer->importExternal(self::URL, 'favicon', 'favicon');
        self::assertSame('favicon', $media->getAlt());
        self::assertSame('favicon-dd93.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());

        $media = $importer->importExternal(self::URL, 'favicon', 'favicon', false);
        self::assertSame('favicon.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());

        $media = $importer->importExternal(self::URL, 'favicon from pied web');
        self::assertSame('favicon-from-pied-web-dd93.png', $media->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());
    }
}
