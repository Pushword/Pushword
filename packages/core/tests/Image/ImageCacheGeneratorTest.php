<?php

namespace Pushword\Core\Tests\Image;

use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Service\MediaCacheStorageAdapter;
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
    private function createGenerator(array $filterSets = [], string $imageDriver = 'auto'): ImageCacheGenerator
    {
        self::bootKernel();
        $mediaStorage = $this->createMediaStorageAdapter();
        $imageReader = new ImageReader($mediaStorage, $imageDriver);
        $imageEncoder = new ImageEncoder();
        $imageCacheManager = new ImageCacheManager($filterSets, $this->publicMediaDir, $this->tmpPublicDir.'/'.$this->publicMediaDir, $mediaStorage);

        $backgroundTaskDispatcher = self::getContainer()->get(BackgroundTaskDispatcherInterface::class);

        return new ImageCacheGenerator($imageReader, $imageEncoder, $imageCacheManager, $backgroundTaskDispatcher, $mediaStorage);
    }

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function createCacheManager(array $filterSets = []): ImageCacheManager
    {
        return new ImageCacheManager($filterSets, $this->publicMediaDir, $this->tmpPublicDir.'/'.$this->publicMediaDir, $this->createMediaStorageAdapter());
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

    public function testGeneratedVariantsArePublishedToRemoteCache(): void
    {
        self::bootKernel();
        $storage = new Flysystem(new InMemoryFilesystemAdapter());
        $mediaStorage = $this->createMediaStorageAdapter();
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scaleDown' => [1600]]]];
        $cacheDir = $this->tmpPublicDir.'/'.$this->publicMediaDir;
        $cacheManager = new ImageCacheManager(
            $filters,
            $this->publicMediaDir,
            $cacheDir,
            $mediaStorage,
            mediaCacheStorage: new MediaCacheStorageAdapter($storage, isLocal: false),
        );
        $generator = new ImageCacheGenerator(
            new ImageReader($mediaStorage),
            new ImageEncoder(),
            $cacheManager,
            self::getContainer()->get(BackgroundTaskDispatcherInterface::class),
            $mediaStorage,
        );

        $generator->generateFilteredCache(__DIR__.'/../Service/blank.jpg', $filters);

        self::assertTrue($storage->fileExists('xl/blank.jpg'));
        self::assertTrue($storage->fileExists('xl/blank.webp'));
        self::assertGreaterThan(0, $storage->fileSize('xl/blank.jpg'));
        self::assertGreaterThan(0, $storage->fileSize('xl/blank.webp'));
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

    /**
     * A gd decode is one allocation of width × height × 4 bytes, counted against
     * memory_limit — overrunning it is a fatal no catch survives, and in production it
     * killed the flush that had already renamed the file on disk. Anything that does not
     * fit must be declined before the decode, not after.
     */
    public function testDecodeFitsInOnlyAcceptsWhatIsLeftOfTheLimit(): void
    {
        // 48 Mpx (the production master) needs ~230 MB with headroom.
        self::assertFalse(ImageCacheGenerator::decodeFitsIn(9237, 5195, 512 * 1024 ** 2, 420 * 1024 ** 2));
        self::assertTrue(ImageCacheGenerator::decodeFitsIn(9237, 5195, 512 * 1024 ** 2, 40 * 1024 ** 2));

        // No limit: nothing to overrun.
        self::assertTrue(ImageCacheGenerator::decodeFitsIn(9237, 5195, null, 420 * 1024 ** 2));

        // The bitmap alone fitting is not enough — the 1.2 headroom decides.
        self::assertFalse(ImageCacheGenerator::decodeFitsIn(1000, 1000, 4_500_000, 0));
        self::assertTrue(ImageCacheGenerator::decodeFitsIn(1000, 1000, 5_000_000, 0));
    }

    public function testMemoryLimitInBytesReadsTheShorthand(): void
    {
        $initial = ini_get('memory_limit');

        try {
            ini_set('memory_limit', '512M');
            self::assertSame(512 * 1024 ** 2, ImageCacheGenerator::memoryLimitInBytes());

            ini_set('memory_limit', '1G');
            self::assertSame(1024 ** 3, ImageCacheGenerator::memoryLimitInBytes());

            ini_set('memory_limit', '536870912'); // plain bytes, what a php.ini often holds
            self::assertSame(536870912, ImageCacheGenerator::memoryLimitInBytes());

            ini_set('memory_limit', '-1');
            self::assertNull(ImageCacheGenerator::memoryLimitInBytes());
        } finally {
            ini_set('memory_limit', '' === $initial ? '-1' : $initial);
        }
    }

    public function testQuickPreviewCopiesTheOriginalAndFillsMetadata(): void
    {
        $generator = $this->createGenerator(['md' => ['quality' => 80, 'filters' => ['scaleDown' => [992]]]]);
        $mediaStorage = $this->createMediaStorageAdapter();

        $probe = 'quick-preview-probe-'.getmypid().'.png';
        $probePath = $mediaStorage->getLocalPath($probe);
        $gd = imagecreatetruecolor(40, 20);
        imagepng($gd, $probePath);

        $media = new Media();
        $media->setFileName($probe);

        try {
            self::assertNotNull($generator->generateQuickPreview($media));
            self::assertFileExists($this->tmpPublicDir.'/'.$this->publicMediaDir.'/md/'.$probe);
            self::assertSame(40, $media->getWidth());
            self::assertSame(20, $media->getHeight());
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', (string) $media->getMainColor());
        } finally {
            @unlink($probePath);
        }
    }

    /**
     * The decode is declined, not attempted and caught: overrunning the limit is a fatal.
     * The preview copy is still written, and the metadata is left to `pw:image:cache`.
     */
    public function testQuickPreviewDeclinesADecodeThatWouldNotFit(): void
    {
        $generator = $this->createGenerator(['md' => ['quality' => 80, 'filters' => ['scaleDown' => [992]]]], 'gd');
        $mediaStorage = $this->createMediaStorageAdapter();

        // 16 Mpx: a 64 MB bitmap, but a few KB on disk — the file never has to be big.
        $probe = 'oversized-probe-'.getmypid().'.png';
        $probePath = $mediaStorage->getLocalPath($probe);
        imagepng(imagecreatetruecolor(4000, 4000), $probePath);

        $media = new Media();
        $media->setFileName($probe);

        $initial = ini_get('memory_limit');

        try {
            // Headroom the assertions can live in, far below the 76 MB the decode would ask for.
            ini_set('memory_limit', (string) (memory_get_usage(true) + 32 * 1024 ** 2));

            self::assertNull($generator->generateQuickPreview($media), 'a decode that does not fit must be declined');
            self::assertNull($media->getWidth(), 'metadata is left to the background pw:image:cache');
        } finally {
            ini_set('memory_limit', $initial);
            @unlink($probePath);
        }

        self::assertFileExists(
            $this->tmpPublicDir.'/'.$this->publicMediaDir.'/md/'.$probe,
            'the preview copy is a file copy, it does not depend on decoding',
        );
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
