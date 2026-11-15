<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Image;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\ImageCachePolicy;
use Pushword\Core\Image\ImageScratchFile;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class ImageCachePolicyTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pushword-image-budget-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir([$this->directory.'/md', $this->directory.'/xs', $this->directory.'/locks']);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testEvictsOldestVariantsUntilTheirMeasuredSizeFits(): void
    {
        $this->write('md/old.webp', 5, 10);
        $this->write('xs/middle.webp', 4, 20);
        $this->write('md/new.webp', 6, 30);

        $this->policy(10)->prune();

        self::assertFileDoesNotExist($this->directory.'/md/old.webp');
        self::assertSame(4, filesize($this->directory.'/xs/middle.webp'));
        self::assertSame(6, filesize($this->directory.'/md/new.webp'));
    }

    public function testDisabledModeAndUnlimitedBudgetPreserveFiles(): void
    {
        $this->write('md/photo.webp', 20, 10);
        $this->policy(1, false)->prune();
        self::assertFileExists($this->directory.'/md/photo.webp');
        $this->policy(0)->prune();
        self::assertSame(20, filesize($this->directory.'/md/photo.webp'));
    }

    public function testOversizedVariantDoesNotEvictFilesThatFitTheBudget(): void
    {
        $this->write('md/old.webp', 4, 10);
        $this->write('xs/new.webp', 6, 20);
        $this->write('md/oversized.webp', 100, 30);

        $this->policy(10)->prune();

        self::assertFileDoesNotExist($this->directory.'/md/oversized.webp');
        self::assertFileExists($this->directory.'/md/old.webp');
        self::assertFileExists($this->directory.'/xs/new.webp');
        self::assertSame(4, filesize($this->directory.'/md/old.webp'));
        self::assertSame(6, filesize($this->directory.'/xs/new.webp'));
    }

    public function testNeverDeletesOriginalsOtherCachesSymlinksOrScratchFiles(): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->directory.'/og', $this->directory.'/other']);
        $this->write('original.jpg', 100, 1);
        $this->write('og/preview.png', 100, 1);
        $this->write('other/photo.webp', 100, 1);
        $scratch = ImageScratchFile::pathFor($this->directory.'/md/photo.webp', 'enc');
        file_put_contents($scratch, str_repeat('x', 100));
        $filesystem->symlink('../original.jpg', $this->directory.'/md/original.jpg');
        $filesystem->remove($this->directory.'/xs');
        $filesystem->symlink($this->directory.'/other', $this->directory.'/xs');
        $this->write('md/evict.webp', 20, 1);

        $this->policy(1)->prune();

        self::assertFileDoesNotExist($this->directory.'/md/evict.webp');
        foreach (['original.jpg', 'og/preview.png', 'other/photo.webp', 'md/original.jpg', 'xs/photo.webp'] as $file) {
            self::assertSame(100, filesize($this->directory.'/'.$file));
        }

        self::assertFileExists($scratch);
        self::assertTrue(is_link($this->directory.'/md/original.jpg'));
        self::assertTrue(is_link($this->directory.'/xs'));
    }

    public function testActiveVariantIsProtectedUntilItsLockIsReleased(): void
    {
        $this->write('md/active.webp', 20, 1);
        $policy = $this->policy(1);
        $lock = $policy->createVariantLock($this->directory.'/md/active.webp');
        self::assertTrue($lock->acquire());
        $contender = $this->policy(1)->createVariantLock($this->directory.'/md/active.webp');
        self::assertFalse($contender->acquire());

        try {
            $policy->prune();
            self::assertFileExists($this->directory.'/md/active.webp');
        } finally {
            $lock->release();
        }

        $policy->prune();
        self::assertFileDoesNotExist($this->directory.'/md/active.webp');
    }

    public function testEmptyCacheNeedsNoFiles(): void
    {
        $this->policy(1)->prune();
        self::assertSame([], glob($this->directory.'/md/*'));
    }

    private function write(string $file, int $size, int $time): void
    {
        file_put_contents($this->directory.'/'.$file, str_repeat('x', $size));
        touch($this->directory.'/'.$file, $time);
    }

    private function policy(int $limit, bool $enabled = true): ImageCachePolicy
    {
        return new ImageCachePolicy($enabled, $limit, $this->directory, ['md' => [], 'xs' => []], new LockFactory(new FlockStore($this->directory.'/locks')));
    }
}
