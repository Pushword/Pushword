<?php

namespace Pushword\Core\Tests\Image;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageOptimizer;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use RuntimeException;
use Spatie\ImageOptimizer\OptimizerChain;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The Spatie optimizers rewrite their target in place (cwebp does `-o samefile`),
 * truncating it at open. A process killed mid-encode — OOM or the per-optimizer
 * timeout, worst on large variants under parallel batch load — would leave the
 * live derivative at 0 bytes, served forever as a broken image. ImageOptimizer
 * must therefore optimize into a throwaway copy and swap it in only when the
 * result is a complete, decodable image.
 */
#[Group('integration')]
final class ImageOptimizerTest extends KernelTestCase
{
    use PathTrait;

    /** @var array<string, array<string, mixed>> */
    private const array MD_FILTER = ['md' => ['quality' => 80, 'filters' => ['scaleDown' => [100]], 'formats' => ['webp']]];

    private string $tmpPublicDir;

    protected function setUp(): void
    {
        $this->tmpPublicDir = sys_get_temp_dir().'/pushword-optimizer-test-'.getmypid();
        new Filesystem()->mkdir($this->tmpPublicDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->tmpPublicDir);
        parent::tearDown();
    }

    public function testKeepsValidVariantWhenOptimizerTruncatesAndThrows(): void
    {
        [$media, $webpPath, $original] = $this->prepareValidVariant();

        // cwebp opened `-o samefile` (truncating it) then was killed → empty temp + throw.
        $optimizer = $this->optimizer($this->chain(static function (string $in, ?string $out): never {
            file_put_contents((string) $out, '');

            throw new RuntimeException('optimizer process killed');
        }));

        $optimizer->optimizeFilter($media, 'md');

        self::assertSame($original, file_get_contents($webpPath), 'A killed optimizer must leave the valid derivative untouched');
        $this->assertNoLeftoverTempFile($webpPath);
    }

    public function testKeepsValidVariantWhenOptimizerTruncatesSilently(): void
    {
        [$media, $webpPath, $original] = $this->prepareValidVariant();

        // Even if the failure is swallowed (Spatie's default handler), a 0-byte
        // result must never be promoted over the valid file.
        $optimizer = $this->optimizer($this->chain(static function (string $in, ?string $out): void {
            file_put_contents((string) $out, '');
        }));

        $optimizer->optimizeFilter($media, 'md');

        self::assertSame($original, file_get_contents($webpPath), 'A silent 0-byte optimize must leave the valid derivative untouched');
        $this->assertNoLeftoverTempFile($webpPath);
    }

    public function testSwapsInOptimizedResultOnSuccess(): void
    {
        [$media, $webpPath, $source, $generator, $cacheManager] = $this->prepareValidVariantWithServices();

        // A genuinely smaller, valid webp the "optimizer" produces (a different width
        // proves the file was actually replaced, not merely left in place).
        $generator->generateFilteredCache($source, ['probe' => ['quality' => 80, 'filters' => ['scaleDown' => [40]], 'formats' => ['webp']]]);
        $optimizedBytes = (string) file_get_contents($cacheManager->getFilterPath($media, 'probe', 'webp'));

        $optimizer = new ImageOptimizer($cacheManager, $generator, $this->chain(static function (string $in, ?string $out) use ($optimizedBytes): void {
            file_put_contents((string) $out, $optimizedBytes);
        }));

        $optimizer->optimizeFilter($media, 'md');

        $size = getimagesize($webpPath);
        self::assertIsArray($size);
        self::assertSame(40, $size[0], 'A successful optimize must replace the derivative with its output');
        $this->assertNoLeftoverTempFile($webpPath);
    }

    /**
     * @return array{Media, string, string}
     */
    private function prepareValidVariant(): array
    {
        [$media, $webpPath] = $this->prepareValidVariantWithServices();

        return [$media, $webpPath, (string) file_get_contents($webpPath)];
    }

    /**
     * Build a real, non-empty `md` webp variant (100px wide) from a 200x100 source
     * and return everything a test needs to exercise the optimize step against it.
     *
     * @return array{Media, string, string, ImageCacheGenerator, ImageCacheManager}
     */
    private function prepareValidVariantWithServices(): array
    {
        $source = $this->tmpPublicDir.'/optimizer-src-'.getmypid().'.png';
        imagepng(imagecreatetruecolor(200, 100), $source);

        $generator = $this->createGenerator(self::MD_FILTER);
        $cacheManager = $this->createCacheManager(self::MD_FILTER);
        $generator->generateFilteredCache($source, self::MD_FILTER);

        $media = new Media();
        $media->setFileName(basename($source));

        $webpPath = $cacheManager->getFilterPath($media, 'md', 'webp');
        self::assertFileExists($webpPath);
        self::assertGreaterThan(0, (int) filesize($webpPath));

        return [$media, $webpPath, $source, $generator, $cacheManager];
    }

    private function optimizer(OptimizerChain $chain): ImageOptimizer
    {
        return new ImageOptimizer($this->createCacheManager(self::MD_FILTER), $this->createGenerator(self::MD_FILTER), $chain);
    }

    /**
     * @param callable(string, ?string): void $behavior
     */
    private function chain(callable $behavior): OptimizerChain
    {
        return new class($behavior) extends OptimizerChain {
            /** @var callable(string, ?string): void */
            private $behavior;

            /**
             * @param callable(string, ?string): void $behavior
             */
            public function __construct(callable $behavior)
            {
                parent::__construct();
                $this->behavior = $behavior;
            }

            #[Override]
            public function optimize(string $pathToImage, ?string $pathToOutput = null): void
            {
                ($this->behavior)($pathToImage, $pathToOutput);
            }
        };
    }

    private function assertNoLeftoverTempFile(string $webpPath): void
    {
        self::assertSame([], glob(\dirname($webpPath).'/*.tmp') ?: [], 'The throwaway optimize temp file must be cleaned up');
    }

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function createGenerator(array $filterSets): ImageCacheGenerator
    {
        self::bootKernel();
        $mediaStorage = $this->createMediaStorageAdapter();

        return new ImageCacheGenerator(
            new ImageReader($mediaStorage),
            new ImageEncoder(),
            new ImageCacheManager($filterSets, $this->tmpPublicDir, $this->publicMediaDir, $mediaStorage),
            self::getContainer()->get(BackgroundTaskDispatcherInterface::class),
            $mediaStorage,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function createCacheManager(array $filterSets): ImageCacheManager
    {
        return new ImageCacheManager($filterSets, $this->tmpPublicDir, $this->publicMediaDir, $this->createMediaStorageAdapter());
    }

    private function createMediaStorageAdapter(): MediaStorageAdapter
    {
        self::bootKernel();

        /** @var MediaStorageAdapter */
        return self::getContainer()->get(MediaStorageAdapter::class);
    }
}
