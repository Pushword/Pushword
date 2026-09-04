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
final class ImageCacheManagerTest extends KernelTestCase
{
    use PathTrait;

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function createManager(array $filterSets = []): ImageCacheManager
    {
        return new ImageCacheManager($filterSets, $this->publicMediaDir, $this->getMediaCacheDir(), $this->createMediaStorageAdapter());
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
        self::assertSame($this->getMediaCacheDir().'/default/test.png', $manager->getFilterPath('test.png', 'default'));
        self::assertSame($this->getMediaCacheDir().'/default/test.webp', $manager->getFilterPath('test.png', 'default', 'webp'));
    }

    public function testRemoteCacheUsesItsPublicUrlAndIsRemovedFromStorage(): void
    {
        $storage = new Flysystem(new InMemoryFilesystemAdapter());
        $remoteCache = new MediaCacheStorageAdapter(
            $storage,
            isLocal: false,
            publicUrl: 'https://media.example.test/cache',
        );
        $manager = new ImageCacheManager(
            ['xs' => ['formats' => ['webp']]],
            $this->publicMediaDir,
            $this->getMediaCacheDir(),
            $this->createMediaStorageAdapter(),
            mediaCacheStorage: $remoteCache,
        );

        // A cold cache keeps the application route so its controller can generate
        // the missing variant instead of sending the browser to an R2 404.
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.webp', $manager->getBrowserPath('test.jpg', 'xs'));

        $storage->write('xs/test.webp', 'remote-variant');
        self::assertSame(
            'https://media.example.test/cache/xs/test.webp',
            $manager->getBrowserPath('test.jpg', 'xs', checkFileExists: true),
        );

        $manager->remove('test.jpg');
        self::assertFalse($storage->fileExists('xs/test.webp'));
    }

    public function testRemoteDerivativeFreshnessUsesStorageTimestamps(): void
    {
        $filesystem = new Filesystem();
        $fileName = 'remote-freshness-'.getmypid().'.jpg';
        $sourcePath = $this->getMediaDir().'/'.$fileName;
        $filesystem->copy(__DIR__.'/../Service/blank.jpg', $sourcePath, true);
        touch($sourcePath, time() - 10);

        $storage = new Flysystem(new InMemoryFilesystemAdapter());
        $storage->write('xs/'.pathinfo($fileName, \PATHINFO_FILENAME).'.webp', 'remote-variant');

        $manager = new ImageCacheManager(
            ['xs' => ['formats' => ['webp']]],
            $this->publicMediaDir,
            $this->getMediaCacheDir(),
            $this->createMediaStorageAdapter(),
            mediaCacheStorage: new MediaCacheStorageAdapter($storage, isLocal: false),
        );
        $media = new Media();
        $media->setFileName($fileName);

        try {
            self::assertTrue($manager->isFilterCacheFresh($media, 'xs'));

            touch($sourcePath, time() + 10);
            clearstatcache(true, $sourcePath);
            self::assertFalse($manager->isFilterCacheFresh($media, 'xs'));
        } finally {
            $filesystem->remove($sourcePath);
        }
    }

    public function testSkippedFilterPublishesDefaultVariantToRemoteCache(): void
    {
        $filesystem = new Filesystem();
        $fileName = 'remote-filter-copy-'.getmypid().'.jpg';
        $defaultPath = $this->getMediaCacheDir().'/default/'.pathinfo($fileName, \PATHINFO_FILENAME).'.webp';
        $filesystem->dumpFile($defaultPath, 'default-variant');
        $storage = new Flysystem(new InMemoryFilesystemAdapter());
        $manager = new ImageCacheManager(
            ['xs' => ['formats' => ['webp']]],
            $this->publicMediaDir,
            $this->getMediaCacheDir(),
            $this->createMediaStorageAdapter(),
            mediaCacheStorage: new MediaCacheStorageAdapter($storage, isLocal: false),
        );
        $media = new Media();
        $media->setFileName($fileName);

        try {
            $manager->symlinkFilterToDefault($media, 'xs');

            self::assertSame('default-variant', $storage->read('xs/'.pathinfo($fileName, \PATHINFO_FILENAME).'.webp'));
        } finally {
            $manager->remove($media);
            $filesystem->remove($defaultPath);
        }
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

    public function testZeroByteVariantIsTreatedAsMissing(): void
    {
        $fs = new Filesystem();

        // Real source so isFilterCacheFresh reaches the variant check. Unique per
        // worker: the md/ variant dir is shared across paratest workers.
        $probe = 'zero-byte-probe-'.getmypid().'.jpg';
        $slug = 'zero-byte-probe-'.getmypid();
        $fs->copy(__DIR__.'/../Service/blank.jpg', $this->getMediaDir().'/'.$probe, true);

        $filters = ['md' => ['quality' => 90, 'filters' => ['scaleDown' => [992]], 'formats' => ['webp', 'original']]];
        $manager = $this->createManager($filters);
        $manager->remove($probe);

        $mdDir = $this->getMediaCacheDir().'/md';
        $fs->mkdir($mdDir);
        // Valid original preview beside a transient 0-byte webp (the production failure mode).
        $fs->dumpFile($mdDir.'/'.$slug.'.jpg', 'non-empty-jpeg-preview');
        $fs->dumpFile($mdDir.'/'.$slug.'.webp', '');

        $media = new Media();
        $media->setFileName($probe);

        // Renderer must skip the 0-byte webp and serve the usable original.
        self::assertSame(
            '/'.$this->publicMediaDir.'/md/'.$slug.'.jpg',
            $manager->getBrowserPath($media, 'md', checkFileExists: true),
        );

        // A 0-byte variant is stale, so pw:image:cache regenerates it instead of skipping forever.
        self::assertFalse($manager->isFilterCacheFresh($media, 'md'));

        $manager->remove($probe);
        $fs->remove($this->getMediaDir().'/'.$probe);
    }

    public function testDimensionsComeFromTheSourceWhileTheVariantIsNotCachedYet(): void
    {
        $fs = new Filesystem();

        // A cold cache is the normal state right after a deploy — and the state every
        // test worker starts in. Reading dimensions must not take the render down:
        // `image.html.twig` asks for them whenever a media carries none of its own.
        $probe = 'cold-cache-probe-'.getmypid().'.jpg';
        $fs->copy(__DIR__.'/../Service/blank.jpg', $this->getMediaDir().'/'.$probe, true);

        $manager = $this->createManager(['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]]]]);
        $manager->remove($probe);

        $source = getimagesize($this->getMediaDir().'/'.$probe);
        self::assertNotFalse($source);

        $dimensions = $manager->getDimensions($probe);
        self::assertSame($source[0], $dimensions->width);
        self::assertSame($source[1], $dimensions->height);

        $fs->remove($this->getMediaDir().'/'.$probe);
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

    public function testIsChainableDownscale(): void
    {
        $manager = $this->createManager([
            'default' => ['quality' => 90, 'filters' => ['scaleDown' => [1980, 1280]]],
            'xl' => ['quality' => 90, 'filters' => ['scaleDown' => [1600]]],
            'thumb' => ['quality' => 80, 'filters' => ['coverDown' => [330, 330]]],
            'height_300' => ['quality' => 82, 'filters' => ['scaleDown' => [null, 300]]],
            'empty' => ['quality' => 90, 'filters' => []],
        ]);

        // Pure width scaleDown (with or without a height cap) is chainable.
        self::assertTrue($manager->isChainableDownscale('default'));
        self::assertTrue($manager->isChainableDownscale('xl'));

        // Crops and height-only scaleDown must derive from the full source: not chainable.
        self::assertFalse($manager->isChainableDownscale('thumb'));
        self::assertFalse($manager->isChainableDownscale('height_300'));

        // No filters / unknown filter is never chainable.
        self::assertFalse($manager->isChainableDownscale('empty'));
        self::assertFalse($manager->isChainableDownscale('nonexistent'));
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

    public function testIsAllCacheFreshChecksThumbFirst(): void
    {
        $this->ensureMediaFileExists();

        // Use a per-worker-unique source copy: this test removes the media's cache,
        // and the variant dir (public/media/{filter}) is shared across all paratest
        // workers. Operating on the shared piedweb-logo would delete variants other
        // workers are reading (static generation, MediaCacheController), causing flakes.
        $probe = 'cache-fresh-probe-'.getmypid().'.png';
        new Filesystem()->copy($this->getMediaDir().'/piedweb-logo.png', $this->getMediaDir().'/'.$probe, true);

        $filters = [
            'default' => ['quality' => 90, 'filters' => ['scaleDown' => [1980, 1280]], 'formats' => ['original', 'webp']],
            'thumb' => ['quality' => 80, 'filters' => ['coverDown' => [330, 330]], 'formats' => ['webp']],
        ];

        $manager = $this->createManager($filters);

        $media = new Media();
        $media->setFileName($probe);

        // Remove thumb cache to force a stale thumb
        $manager->remove($probe);

        // No cache exists → should return false (thumb stale = early exit)
        self::assertFalse($manager->isAllCacheFresh($media));
    }

    public function testRemoveDeletesRootPublicSymlink(): void
    {
        $manager = $this->createManager(['default' => []]);
        $publicMediaPath = $this->getMediaCacheDir();
        new Filesystem()->mkdir($publicMediaPath);

        // Create a symlink like ensurePublicSymlink does
        $symlinkPath = $publicMediaPath.'/test-remove.pdf';
        symlink('../../media/test-remove.pdf', $symlinkPath);
        self::assertTrue(is_link($symlinkPath));

        $manager->remove('test-remove.pdf');

        self::assertFalse(is_link($symlinkPath), 'Root public symlink should be removed');
    }

    public function testSvgIsNeverPublishedAsAStaticSymlink(): void
    {
        $manager = $this->createManager();
        $publicMediaPath = $this->getMediaCacheDir();
        new Filesystem()->mkdir($publicMediaPath);

        $symlinkPath = $publicMediaPath.'/active.svg';
        symlink('../../media/active.svg', $symlinkPath);
        self::assertTrue(is_link($symlinkPath));

        $media = new Media();
        $media->setFileName('active.svg');

        $manager->ensurePublicSymlink($media);

        self::assertFalse(is_link($symlinkPath));
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
        $generator = new ImageCacheGenerator($imageReader, $imageEncoder, $manager, $backgroundTaskDispatcher, $mediaStorage);

        // Generate the default filter cache first
        $generator->generateFilteredCache($image, ['default' => $filters['default']]);

        $defaultWebp = $this->getMediaCacheDir().'/default/blank.webp';
        self::assertFileExists($defaultWebp);

        // Create symlink for xl -> default
        $media = new Media();
        $media->setFileName('blank.jpg');

        $manager->symlinkFilterToDefault($media, 'xl');

        $xlWebp = $this->getMediaCacheDir().'/xl/blank.webp';
        self::assertTrue(is_link($xlWebp));
        self::assertFileExists($xlWebp);

        // Cleanup
        $manager->remove('blank.jpg');
    }
}
