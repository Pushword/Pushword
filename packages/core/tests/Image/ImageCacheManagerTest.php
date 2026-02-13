<?php

namespace Pushword\Core\Tests\Image;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
class ImageCacheManagerTest extends KernelTestCase
{
    use PathTrait;

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function createManager(array $filterSets = []): ImageCacheManager
    {
        return new ImageCacheManager($filterSets, $this->publicDir, $this->publicMediaDir, $this->createMediaStorageAdapter());
    }

    private function createMediaStorageAdapter(): MediaStorageAdapter
    {
        self::bootKernel();

        /** @var MediaStorageAdapter */
        return self::getContainer()->get(MediaStorageAdapter::class);
    }

    public function testBrowserAndFilterPath(): void
    {
        $manager = $this->createManager(['default' => [], 'xs' => []]);
        $manager->remove('test.png');

        // Default formats = ['original', 'webp'], so webp is returned (priority over original)
        self::assertSame('/'.$this->publicMediaDir.'/default/test.webp', $manager->getBrowserPath('test.png'));
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.webp', $manager->getBrowserPath('test.png', 'xs'));
        // With checkFileExists=true and no files, falls back to original
        self::assertSame('/'.$this->publicMediaDir.'/default/test.png', $manager->getBrowserPath('test.png', checkFileExists: true));
        // getFilterPath always returns the requested path (doesn't check file existence)
        self::assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.png', $manager->getFilterPath('test.png', 'default'));
        self::assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.webp', $manager->getFilterPath('test.png', 'default', 'webp'));
    }

    public function testBrowserPathFirstAvailableFormat(): void
    {
        // With checkFileExists=false (default), returns based on config priority (webp > original)
        $manager = $this->createManager(['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['webp', 'original']]]);
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.webp', $manager->getBrowserPath('test.png', 'xs'));

        // With checkFileExists=true, falls back to original since webp files don't exist
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.png', $manager->getBrowserPath('test.png', 'xs', checkFileExists: true));

        // Test with explicit extension - should always use provided extension
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.webp', $manager->getBrowserPath('test.png', 'xs', 'webp'));
    }

    public function testPreferredModernFormat(): void
    {
        // Test with WebP - should return WebP
        $manager = $this->createManager(['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['original', 'webp']]]);
        self::assertSame('webp', $manager->getPreferredModernFormat('xs'));

        // Test with original only - should return null
        $manager = $this->createManager(['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['original']]]);
        self::assertNull($manager->getPreferredModernFormat('xs'));
    }

    public function testShouldSkipFilter(): void
    {
        $manager = $this->createManager([
            'xl' => ['quality' => 90, 'filters' => ['scaleDown' => [1600]]],
            'sm' => ['quality' => 85, 'filters' => ['scaleDown' => [768]]],
            'thumb' => ['quality' => 80, 'filters' => ['coverDown' => [330, 330]]],
            'height_300' => ['quality' => 82, 'filters' => ['scaleDown' => [null, 300]]],
        ]);

        // 800x600 source: skip xl (800 < 1600), don't skip sm (800 > 768)
        self::assertTrue($manager->shouldSkipFilter('xl', 800, 600));
        self::assertFalse($manager->shouldSkipFilter('sm', 800, 600));

        // coverDown: skip only if source smaller in both dimensions
        self::assertFalse($manager->shouldSkipFilter('thumb', 800, 600));
        self::assertTrue($manager->shouldSkipFilter('thumb', 200, 200));

        // height_300 with null width: skip only if source height <= 300
        self::assertFalse($manager->shouldSkipFilter('height_300', 800, 600));
        self::assertTrue($manager->shouldSkipFilter('height_300', 800, 200));

        // Unknown filter
        self::assertFalse($manager->shouldSkipFilter('nonexistent', 800, 600));
    }

    public function testGetFilterTargetWidth(): void
    {
        $manager = $this->createManager([
            'xl' => ['quality' => 90, 'filters' => ['scaleDown' => [1600]]],
            'thumb' => ['quality' => 80, 'filters' => ['coverDown' => [330, 330]]],
            'height_300' => ['quality' => 82, 'filters' => ['scaleDown' => [null, 300]]],
        ]);

        self::assertSame(1600, $manager->getFilterTargetWidth('xl'));
        self::assertSame(330, $manager->getFilterTargetWidth('thumb'));
        self::assertNull($manager->getFilterTargetWidth('height_300'));
        self::assertNull($manager->getFilterTargetWidth('nonexistent'));
    }

    public function testGetSourceDimensions(): void
    {
        $manager = $this->createManager();

        $this->ensureMediaFileExists();
        $media = new Media();
        $media->setFileName('piedweb-logo.png');

        $dimensions = $manager->getSourceDimensions($media);
        self::assertIsArray($dimensions);
        self::assertGreaterThan(0, $dimensions[0]);
        self::assertGreaterThan(0, $dimensions[1]);

        // Non-existent file
        $media2 = new Media();
        $media2->setFileName('nonexistent.jpg');
        self::assertNull($manager->getSourceDimensions($media2));
    }

    public function testRemoveDeletesRootPublicSymlink(): void
    {
        $manager = $this->createManager(['default' => []]);
        $publicMediaPath = $this->publicDir.'/'.$this->publicMediaDir;
        new Filesystem()->mkdir($publicMediaPath);

        // Create a symlink like ensurePublicSymlink does
        $symlinkPath = $publicMediaPath.'/test-remove.pdf';
        symlink('../../media/test-remove.pdf', $symlinkPath);
        self::assertTrue(is_link($symlinkPath));

        $manager->remove('test-remove.pdf');

        self::assertFalse(is_link($symlinkPath), 'Root public symlink should be removed');
    }

    public function testSymlinkFilterToDefault(): void
    {
        $filters = [
            'default' => ['quality' => 90, 'filters' => ['scaleDown' => [1980, 1280]], 'formats' => ['original', 'webp']],
            'xl' => ['quality' => 90, 'filters' => ['scaleDown' => [1600]], 'formats' => ['webp']],
        ];

        $image = __DIR__.'/../Service/blank.jpg';
        $mediaStorage = $this->createMediaStorageAdapter();
        $imageReader = new ImageReader($mediaStorage);
        $imageEncoder = new ImageEncoder();
        $manager = $this->createManager($filters);

        $backgroundTaskDispatcher = self::getContainer()->get(BackgroundTaskDispatcherInterface::class);
        $generator = new ThumbnailGenerator($imageReader, $imageEncoder, $manager, $backgroundTaskDispatcher, $mediaStorage);

        // Generate the default filter cache first
        $generator->generateFilteredCache($image, ['default' => $filters['default']]);

        $defaultWebp = $this->publicDir.'/'.$this->publicMediaDir.'/default/blank.webp';
        self::assertFileExists($defaultWebp);

        // Create symlink for xl -> default
        $media = new Media();
        $media->setFileName('blank.jpg');

        $manager->symlinkFilterToDefault($media, 'xl');

        $xlWebp = $this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.webp';
        self::assertTrue(is_link($xlWebp));
        self::assertFileExists($xlWebp);

        // Cleanup
        $manager->remove('blank.jpg');
    }
}
