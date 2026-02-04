<?php

namespace Pushword\Core\Tests\Image;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
class ThumbnailGeneratorTest extends KernelTestCase
{
    use PathTrait;

    private string $tmpPublicDir;

    protected function setUp(): void
    {
        $this->tmpPublicDir = sys_get_temp_dir().'/pushword-thumb-test-'.getmypid();
        new Filesystem()->mkdir($this->tmpPublicDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->tmpPublicDir);
        parent::tearDown();
    }

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function createGenerator(array $filterSets = []): ThumbnailGenerator
    {
        self::bootKernel();
        $mediaStorage = $this->createMediaStorageAdapter();
        $imageReader = new ImageReader($mediaStorage);
        $imageEncoder = new ImageEncoder();
        $imageCacheManager = new ImageCacheManager($filterSets, $this->tmpPublicDir, $this->publicMediaDir, $mediaStorage);

        $backgroundTaskDispatcher = self::getContainer()->get(BackgroundTaskDispatcherInterface::class);

        return new ThumbnailGenerator($imageReader, $imageEncoder, $imageCacheManager, $backgroundTaskDispatcher, $mediaStorage);
    }

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function createCacheManager(array $filterSets = []): ImageCacheManager
    {
        return new ImageCacheManager($filterSets, $this->tmpPublicDir, $this->publicMediaDir, $this->createMediaStorageAdapter());
    }

    private function createMediaStorageAdapter(): MediaStorageAdapter
    {
        self::bootKernel();

        /** @var MediaStorageAdapter */
        return self::getContainer()->get(MediaStorageAdapter::class);
    }

    public function testFilterCache(): void
    {
        $image = __DIR__.'/../Service/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scaleDown' => [1600]]]];
        $generator = $this->createGenerator($filters);
        $generator->generateFilteredCache($image, $filters);

        self::assertFileExists($this->tmpPublicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');

        $imgSize = getimagesize($this->tmpPublicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertIsArray($imgSize);
        self::assertSame(1, $imgSize[0]);
        self::assertSame(1, $imgSize[1]);

        $cacheManager = $this->createCacheManager($filters);
        $cacheManager->remove($image);

        $image = __DIR__.'/../Service/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scale' => 1600]]];
        $generator = $this->createGenerator($filters);
        $generator->generateFilteredCache($image, $filters);

        $imgSize = getimagesize($this->tmpPublicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertIsArray($imgSize);
        self::assertSame(1600, $imgSize[0]);

        $cacheManager = $this->createCacheManager($filters);
        $cacheManager->remove($image);
        self::assertFileDoesNotExist($this->tmpPublicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
    }

    public function testFilterCacheWithFormats(): void
    {
        $image = __DIR__.'/../Service/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scaleDown' => [1600]], 'formats' => ['original', 'webp']]];
        $generator = $this->createGenerator($filters);
        $generator->generateFilteredCache($image, $filters);

        self::assertFileExists($this->tmpPublicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertFileExists($this->tmpPublicDir.'/'.$this->publicMediaDir.'/xl/blank.webp');

        $cacheManager = $this->createCacheManager($filters);
        $cacheManager->remove($image);

        self::assertFileDoesNotExist($this->tmpPublicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertFileDoesNotExist($this->tmpPublicDir.'/'.$this->publicMediaDir.'/xl/blank.webp');
    }
}
