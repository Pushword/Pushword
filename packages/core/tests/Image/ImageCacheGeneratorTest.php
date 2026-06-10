<?php

namespace Pushword\Core\Tests\Image;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class ImageCacheGeneratorTest extends KernelTestCase
{
    use PathTrait;

    private string $tmpPublicDir;

    protected function setUp(): void
    {
        $this->tmpPublicDir = sys_get_temp_dir().'/pushword-cache-test-'.getmypid();
        new Filesystem()->mkdir($this->tmpPublicDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->tmpPublicDir);
        parent::tearDown();
    }

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function createGenerator(array $filterSets = []): ImageCacheGenerator
    {
        self::bootKernel();
        $mediaStorage = $this->createMediaStorageAdapter();
        $imageReader = new ImageReader($mediaStorage);
        $imageEncoder = new ImageEncoder();
        $imageCacheManager = new ImageCacheManager($filterSets, $this->tmpPublicDir, $this->publicMediaDir, $mediaStorage);

        $backgroundTaskDispatcher = self::getContainer()->get(BackgroundTaskDispatcherInterface::class);

        return new ImageCacheGenerator($imageReader, $imageEncoder, $imageCacheManager, $backgroundTaskDispatcher, $mediaStorage);
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

    public function testMainColorExtraction(): void
    {
        $generator = $this->createGenerator([
            'default' => ['quality' => 80, 'filters' => ['scaleDown' => [100]]],
        ]);

        $media = new Media();
        $media->setProjectDir($this->projectDir);
        $media->setStoreIn($this->getMediaDir());
        $media->setFileName('blank.jpg');

        // Copy test image into media dir so ImageReader can find it
        $mediaStorage = $this->createMediaStorageAdapter();
        $mediaPath = $mediaStorage->getLocalPath('blank.jpg');
        if (! file_exists($mediaPath)) {
            new Filesystem()->copy(__DIR__.'/../Service/blank.jpg', $mediaPath);
        }

        $generator->generateCache($media, force: true);

        self::assertNotNull($media->getMainColor());
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $media->getMainColor());
    }

    public function testHeightAndCropFiltersDeriveFromSourceNotChain(): void
    {
        // Regression: height-only (height_300) and crop (thumb/coverDown) filters must
        // derive from the full source image, not from the progressively-downsized
        // responsive chain. Previously height_300 inherited the xs/thumb output and
        // produced a tiny square crop (259x259 from a 1980x891 source) instead of a
        // proportional 667x300 image, and thumb shrank to 259x259 instead of 330x330.
        $mediaStorage = $this->createMediaStorageAdapter();

        $probe = 'height-chain-probe-'.getmypid().'.png';
        $probePath = $mediaStorage->getLocalPath($probe);
        $gd = imagecreatetruecolor(1980, 891);
        imagepng($gd, $probePath);

        $filters = [
            'default' => ['quality' => 90, 'filters' => ['scaleDown' => [1980, 1280]], 'formats' => ['webp']],
            'xl' => ['quality' => 90, 'filters' => ['scaleDown' => [1600]], 'formats' => ['webp']],
            'md' => ['quality' => 90, 'filters' => ['scaleDown' => [992]], 'formats' => ['webp']],
            'xs' => ['quality' => 90, 'filters' => ['scaleDown' => [576]], 'formats' => ['webp']],
            'thumb' => ['quality' => 80, 'filters' => ['coverDown' => [330, 330]], 'formats' => ['webp']],
            'height_300' => ['quality' => 90, 'filters' => ['scaleDown' => [null, 300]], 'formats' => ['webp']],
        ];

        $generator = $this->createGenerator($filters);
        $cacheManager = $this->createCacheManager($filters);

        $media = new Media();
        $media->setFileName($probe);

        try {
            $generator->generateCache($media, force: true);

            $heightSize = getimagesize($cacheManager->getFilterPath($probe, 'height_300', 'webp'));
            self::assertIsArray($heightSize);
            self::assertSame(300, $heightSize[1], 'height_300 must be 300px tall (derived from the full source)');
            self::assertEqualsWithDelta(667, $heightSize[0], 1, 'height_300 width must stay proportional, not a square crop');

            $thumbSize = getimagesize($cacheManager->getFilterPath($probe, 'thumb', 'webp'));
            self::assertIsArray($thumbSize);
            self::assertSame([330, 330], [$thumbSize[0], $thumbSize[1]], 'thumb must crop the full source to 330x330');
        } finally {
            $cacheManager->remove($probe);
            @unlink($probePath);
        }
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
