<?php

namespace Pushword\Core\Tests\Image;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
}
