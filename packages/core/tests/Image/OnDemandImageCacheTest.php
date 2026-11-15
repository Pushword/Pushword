<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Image;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Controller\MediaCacheController;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageCachePolicy;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageOptimizer;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaCacheStorageAdapter;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Spatie\ImageOptimizer\OptimizerChain;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

#[Group('integration')]
final class OnDemandImageCacheTest extends KernelTestCase
{
    use PathTrait;

    private const array FILTERS = [
        'md' => ['filters' => ['scaleDown' => [992]], 'formats' => ['original', 'webp']],
        'xs' => ['filters' => ['scaleDown' => [100]], 'formats' => ['webp']],
        'source' => ['filters' => ['scaleDown' => [100]], 'formats' => ['original']],
    ];

    private string $directory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->ensureMediaFileExists();
        $this->directory = sys_get_temp_dir().'/pushword-on-demand-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir($this->directory.'/locks');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
        parent::tearDown();
    }

    public function testGeneratesOnlyRequestedFormatWithPublicCacheHeadersAndStableUrls(): void
    {
        [$controller, $manager] = $this->services();
        $response = $controller->generate('md', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->has('Last-Modified'));
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
        $bytes = $this->body($response);
        self::assertSame((string) \strlen($bytes), $response->headers->get('Content-Length'));
        self::assertIsArray(getimagesizefromstring($bytes));
        self::assertFileExists($this->directory.'/md/piedweb-logo.webp');
        self::assertFileDoesNotExist($this->directory.'/md/piedweb-logo.png');
        self::assertDirectoryDoesNotExist($this->directory.'/xs');
        self::assertSame('/media/md/piedweb-logo.webp', $manager->getBrowserPath('piedweb-logo.png', 'md', 'webp'));

        touch($this->directory.'/md/piedweb-logo.webp', 10);
        $second = $controller->generate('md', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $second);
        self::assertSame($bytes, $this->body($second));
        self::assertSame(10, filemtime($this->directory.'/md/piedweb-logo.webp'));
    }

    public function testOversizedVariantIsServedWithoutRemainingInTheCache(): void
    {
        [$controller] = $this->services(1);
        $first = $controller->generate('md', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $first);
        self::assertFileDoesNotExist($this->directory.'/md/piedweb-logo.webp');
        $second = $controller->generate('md', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $second);
        self::assertFileDoesNotExist($this->directory.'/md/piedweb-logo.webp');

        $bytes = $this->body($first);
        self::assertGreaterThan(1, \strlen($bytes));
        self::assertSame($bytes, $this->body($second));
    }

    public function testOriginalFormatCanBeRequestedWithoutGeneratingWebp(): void
    {
        [$controller] = $this->services();
        $response = $controller->generate('md', 'piedweb-logo.png');
        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertIsArray(getimagesizefromstring($this->body($response)));
        self::assertFileExists($this->directory.'/md/piedweb-logo.png');
        self::assertFileDoesNotExist($this->directory.'/md/piedweb-logo.webp');
    }

    public function testNativeWebpWorksWithAnOriginalOnlyFilter(): void
    {
        $fileName = 'on-demand-native-'.bin2hex(random_bytes(4)).'.webp';
        imagewebp(imagecreatetruecolor(40, 20), $this->getMediaDir().'/'.$fileName);
        $media = new Media()->setFileName($fileName)->setMimeType('image/webp');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($media);
        $em->flush();

        try {
            [$controller] = $this->services();
            $response = $controller->generate('source', $fileName);
            self::assertInstanceOf(StreamedResponse::class, $response);
            self::assertSame('image/webp', $response->headers->get('Content-Type'));
            self::assertIsArray(getimagesizefromstring($this->body($response)));
            self::assertFileExists($this->directory.'/source/'.$fileName);
        } finally {
            $em->remove($media);
            $em->flush();
        }
    }

    public function testAnOpenResponseSurvivesEvictionByAnotherVariantRequest(): void
    {
        [$controller] = $this->services();
        $response = $controller->generate('md', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $response);
        $path = $this->directory.'/md/piedweb-logo.webp';
        $expected = file_get_contents($path);
        $limit = filesize($path);
        self::assertIsInt($limit);
        touch($path, 10);

        [$controller] = $this->services($limit);
        $other = $controller->generate('xs', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $other);
        self::assertFileDoesNotExist($path);
        self::assertFileExists($this->directory.'/xs/piedweb-logo.webp');
        self::assertSame($expected, $this->body($response));
        self::assertNotSame('', $this->body($other));

        $rebuilt = $controller->generate('md', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $rebuilt);
        self::assertSame($expected, $this->body($rebuilt));
    }

    public function testPreviewAndBackgroundGenerationKeepMetadataWithoutWritingVariants(): void
    {
        [, , $generator] = $this->services(queueMetadata: true);
        $media = $this->media();
        $original = file_get_contents($this->getMediaDir().'/piedweb-logo.png');
        self::assertNotNull($generator->generateQuickPreview($media));
        self::assertFalse($generator->generateCache($media, force: true));
        $generator->runBackgroundCacheGeneration($media->getFileName());

        self::assertNotNull($media->getMainColor());
        self::assertGreaterThan(0, $media->getWidth());
        self::assertSame($original, file_get_contents($this->getMediaDir().'/piedweb-logo.png'));
        self::assertDirectoryDoesNotExist($this->directory.'/md');
        self::assertDirectoryDoesNotExist($this->directory.'/xs');
    }

    public function testOptimizerDoesNotRecreateUnrequestedFormatsOrFilters(): void
    {
        [$controller, $manager, $generator, $policy] = $this->services();
        $response = $controller->generate('md', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $response);
        $this->body($response);

        new ImageOptimizer($manager, $generator, new OptimizerChain(), $policy)->optimize($this->media());

        self::assertFileExists($this->directory.'/md/piedweb-logo.webp');
        self::assertFileDoesNotExist($this->directory.'/md/piedweb-logo.png');
        self::assertDirectoryDoesNotExist($this->directory.'/xs');
    }

    public function testOptimizerDropsEmptyVariantsForLazyRegeneration(): void
    {
        [$controller, $manager, $generator, $policy] = $this->services();
        $path = $manager->getFilterPath('piedweb-logo.png', 'md', 'webp');
        new Filesystem()->dumpFile($path, '');

        new ImageOptimizer($manager, $generator, new OptimizerChain(), $policy)->optimize($this->media());

        self::assertFileDoesNotExist($path);
        self::assertFileDoesNotExist($this->directory.'/md/piedweb-logo.png');
        $response = $controller->generate('md', 'piedweb-logo.webp');
        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertIsArray(getimagesizefromstring($this->body($response)));
    }

    public function testUnconfiguredFormatReturns404(): void
    {
        [$controller] = $this->services();
        $this->expectException(NotFoundHttpException::class);
        $controller->generate('xs', 'piedweb-logo.png');
    }

    /** @return array{MediaCacheController, ImageCacheManager, ImageCacheGenerator, ImageCachePolicy} */
    private function services(int $limit = 0, bool $queueMetadata = false): array
    {
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $policy = new ImageCachePolicy(true, $limit, $this->directory, self::FILTERS, new LockFactory(new FlockStore($this->directory.'/locks')));
        $cacheStorage = new MediaCacheStorageAdapter(new Flysystem(new LocalFilesystemAdapter($this->directory)));
        $manager = new ImageCacheManager(self::FILTERS, 'media', $this->directory, $mediaStorage, mediaCacheStorage: $cacheStorage, imageCachePolicy: $policy);
        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects($queueMetadata ? self::once() : self::never())->method('dispatch')
            ->with(self::anything(), ['php', 'bin/console', 'pw:image:cache', 'piedweb-logo.png'], 'pw:image:cache');
        $generator = new ImageCacheGenerator(new ImageReader($mediaStorage), new ImageEncoder(), $manager, $dispatcher, $mediaStorage);
        $controller = new MediaCacheController(self::getContainer()->get(MediaRepository::class), $manager, $generator, $cacheStorage, $policy);

        return [$controller, $manager, $generator, $policy];
    }

    private function media(): Media
    {
        $media = self::getContainer()->get(MediaRepository::class)->findOneByFileNameOrHistory('piedweb-logo.png');
        self::assertInstanceOf(Media::class, $media);

        return $media;
    }

    private function body(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
