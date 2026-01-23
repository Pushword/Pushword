<?php

namespace Pushword\Core\Tests\Service;

use Pushword\Core\Service\ImageManager;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImageManagerTest extends KernelTestCase
{
    use PathTrait;

    private ?ImageManager $imageManager = null;

    private function getManager(): ImageManager
    {
        if (null !== $this->imageManager) {
            return $this->imageManager;
        }

        $mediaStorage = $this->createMediaStorageAdapter();

        return $this->imageManager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);
    }

    private function createMediaStorageAdapter(): MediaStorageAdapter
    {
        self::bootKernel();

        /** @var MediaStorageAdapter */
        return self::getContainer()->get(MediaStorageAdapter::class);
    }

    public function testBrowserAndFilterPath(): void
    {
        // Clean up any leftover files from previous test runs (need to set filters for remove to work)
        $this->getManager()->setFilters(['default' => [], 'xs' => []]);
        $this->getManager()->remove('test.png');

        // getBrowserPath with checkFileExists=false (default) returns based on config priority (avif > webp > original)
        // Default formats = ['original', 'webp'], so webp is returned (priority over original)
        self::assertSame('/'.$this->publicMediaDir.'/default/test.webp', $this->getManager()->getBrowserPath('test.png'));
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.webp', $this->getManager()->getBrowserPath('test.png', 'xs'));
        // With checkFileExists=true and no files, falls back to original
        self::assertSame('/'.$this->publicMediaDir.'/default/test.png', $this->getManager()->getBrowserPath('test.png', checkFileExists: true));
        // getFilterPath always returns the requested path (doesn't check file existence)
        self::assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.png', $this->getManager()->getFilterPath('test.png', 'default'));
        self::assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.webp', $this->getManager()->getFilterPath('test.png', 'default', 'webp'));
        self::assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.avif', $this->getManager()->getFilterPath('test.png', 'default', 'avif'));
    }

    public function testFilterCache(): void
    {
        $image = __DIR__.'/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scaleDown' => [1600]]]];
        $this->getManager()->generateFilteredCache($image, $filters);

        self::assertFileExists($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');

        $imgSize = getimagesize($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertIsArray($imgSize);
        self::assertSame(1, $imgSize[0]);
        self::assertSame(1, $imgSize[1]);

        $this->getManager()->remove($image);
        $image = __DIR__.'/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scale' => 1600]]];
        $this->getManager()->generateFilteredCache($image, $filters);
        $imgSize = getimagesize($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertIsArray($imgSize);
        self::assertSame(1600, $imgSize[0]);

        $this->getManager()->setFilters($filters);
        $this->getManager()->remove($image);
        self::assertFileDoesNotExist($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
    }

    public function testFilterCacheWithFormats(): void
    {
        $image = __DIR__.'/blank.jpg';
        // Test with AVIF and WebP formats
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scaleDown' => [1600]], 'formats' => ['original', 'avif', 'webp']]];
        $this->getManager()->generateFilteredCache($image, $filters);

        self::assertFileExists($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertFileExists($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.avif');
        self::assertFileExists($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.webp');

        $this->getManager()->setFilters($filters);
        $this->getManager()->remove($image);

        self::assertFileDoesNotExist($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertFileDoesNotExist($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.avif');
        self::assertFileDoesNotExist($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.webp');
    }

    public function testPreferredModernFormat(): void
    {
        $mediaStorage = $this->createMediaStorageAdapter();

        // Test with AVIF and WebP - should return AVIF
        $filters = ['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['original', 'avif', 'webp']]];
        $manager = new ImageManager($filters, $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);
        self::assertSame('avif', $manager->getPreferredModernFormat('xs'));

        // Test with WebP only - should return WebP
        $filters = ['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['original', 'webp']]];
        $manager = new ImageManager($filters, $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);
        self::assertSame('webp', $manager->getPreferredModernFormat('xs'));

        // Test with original only - should return null
        $filters = ['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['original']]];
        $manager = new ImageManager($filters, $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);
        self::assertNull($manager->getPreferredModernFormat('xs'));
    }

    public function testBrowserPathFirstAvailableFormat(): void
    {
        $mediaStorage = $this->createMediaStorageAdapter();

        // With checkFileExists=false (default), returns based on config priority (avif > webp > original)
        $filters = ['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['avif', 'webp']]];
        $manager = new ImageManager($filters, $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);
        // Returns avif because it's first in priority and checkFileExists=false
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.avif', $manager->getBrowserPath('test.png', 'xs'));

        // With checkFileExists=true, falls back to original since avif/webp files don't exist
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.png', $manager->getBrowserPath('test.png', 'xs', checkFileExists: true));

        // Test with explicit extension - should always use provided extension (no file check)
        $filters = ['xs' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['avif']]];
        $manager = new ImageManager($filters, $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.webp', $manager->getBrowserPath('test.png', 'xs', 'webp'));

        // Test that when files DO exist, the preferred format is returned (with or without checkFileExists)
        $image = __DIR__.'/blank.jpg';
        $filters = ['testformat' => ['quality' => 85, 'filters' => ['scaleDown' => [576]], 'formats' => ['avif', 'webp', 'original']]];
        $manager = new ImageManager($filters, $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);
        $manager->generateFilteredCache($image, $filters);
        // Returns avif path (both with checkFileExists true and false, since file exists)
        self::assertSame('/'.$this->publicMediaDir.'/testformat/blank.avif', $manager->getBrowserPath('blank.jpg', 'testformat'));
        self::assertSame('/'.$this->publicMediaDir.'/testformat/blank.avif', $manager->getBrowserPath('blank.jpg', 'testformat', checkFileExists: true));
        $manager->remove($image);
    }

    public function testImportExternal(): void
    {
        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon', 'favicon');
        self::assertSame('favicon', $media->getAlt());
        self::assertSame('favicon-dd93.png', $media->getFileName());
        self::assertFileExists($this->mediaDir.'/'.$media->getFileName());

        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon', 'favicon', false);
        self::assertSame('favicon.png', $media->getFileName());
        self::assertFileExists($this->mediaDir.'/'.$media->getFileName());

        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon from pied web');
        self::assertSame('favicon-from-pied-web-dd93.png', $media->getFileName());
        self::assertFileExists($this->mediaDir.'/'.$media->getFileName());

        // Cleanup imported files
        @unlink($this->mediaDir.'/favicon-dd93.png');
        @unlink($this->mediaDir.'/favicon.png');
        @unlink($this->mediaDir.'/favicon-from-pied-web-dd93.png');
    }

    public function testDriverSelection(): void
    {
        $mediaStorage = $this->createMediaStorageAdapter();

        // Test auto driver (should pick imagick if available, else gd)
        $manager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage, 'auto');
        $expectedDriver = \extension_loaded('imagick') ? 'imagick' : 'gd';
        self::assertSame($expectedDriver, $manager->getResolvedDriver());

        // Test explicit gd driver
        $manager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage, 'gd');
        self::assertSame('gd', $manager->getResolvedDriver());

        // Test explicit imagick driver (if available)
        if (\extension_loaded('imagick')) {
            $manager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage, 'imagick');
            self::assertSame('imagick', $manager->getResolvedDriver());
        }
    }

    public function testAvifSourceCacheGeneration(): void
    {
        $mediaStorage = $this->createMediaStorageAdapter();

        $image = __DIR__.'/hato.avif';
        $filters = ['aviftest' => ['quality' => 80, 'filters' => ['scaleDown' => [800]], 'formats' => ['original', 'webp']]];
        $manager = new ImageManager($filters, $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);

        // Generate cache for AVIF source
        $manager->generateFilteredCache($image, $filters);

        // Check that original format (should be AVIF encoded via avifenc) exists
        $avifPath = $this->publicDir.'/'.$this->publicMediaDir.'/aviftest/hato.avif';
        self::assertFileExists($avifPath);

        // Verify the output is valid AVIF (not corrupted HEIC)
        $mimeType = mime_content_type($avifPath);
        self::assertSame('image/avif', $mimeType, 'Output should be valid AVIF, not corrupted');

        // Check dimensions (should be scaled down)
        $imgSize = getimagesize($avifPath);
        self::assertIsArray($imgSize);
        self::assertLessThanOrEqual(800, $imgSize[0], 'Width should be scaled down to 800px or less');

        // WebP version should also exist
        self::assertFileExists($this->publicDir.'/'.$this->publicMediaDir.'/aviftest/hato.webp');

        // Cleanup
        $manager->remove($image);
        self::assertFileDoesNotExist($avifPath);
    }
}
