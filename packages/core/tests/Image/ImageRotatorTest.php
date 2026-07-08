<?php

namespace Pushword\Core\Tests\Image;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Image\ImageRotator;
use Pushword\Core\Service\MediaStorageAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class ImageRotatorTest extends KernelTestCase
{
    private string $tmpPublicDir;

    private string $cacheDir;

    private MediaStorageAdapter $mediaStorage;

    private string $sourceFileName = '';

    protected function setUp(): void
    {
        $this->tmpPublicDir = sys_get_temp_dir().'/pushword-rotate-test-'.getmypid();
        $this->cacheDir = $this->tmpPublicDir.'/media';
        new Filesystem()->mkdir($this->tmpPublicDir);

        self::bootKernel();
        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $this->mediaStorage = $mediaStorage;
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tmpPublicDir);

        if ('' !== $this->sourceFileName) {
            $filesystem->remove($this->mediaStorage->getMediaDir().'/'.$this->sourceFileName);
        }

        parent::tearDown();
    }

    public function testRotateSwapsDimensionsAndRebuildsCache(): void
    {
        $rotator = $this->createRotator();
        $media = $this->createSourceMedia(4, 2);
        $before = $media->getHash();

        $rotator->rotate($media, 90);

        $sourcePath = $this->mediaStorage->getMediaDir().'/'.$this->sourceFileName;
        $size = getimagesize($sourcePath);
        self::assertIsArray($size);
        self::assertSame(2, $size[0], 'width and height must be swapped on disk');
        self::assertSame(4, $size[1]);

        self::assertSame(2, $media->getWidth(), 'entity dimensions must be refreshed');
        self::assertSame(4, $media->getHeight());
        self::assertNotSame($before, $media->getHash(), 'hash must change after rotation');
        self::assertFileExists($this->cacheDir.'/md/'.$this->sourceFileName, 'quick preview cache must be regenerated');
    }

    public function testRotate180KeepsDimensions(): void
    {
        $rotator = $this->createRotator();
        $media = $this->createSourceMedia(4, 2);
        $before = $media->getHash();

        $rotator->rotate($media, 180);

        $size = getimagesize($this->mediaStorage->getMediaDir().'/'.$this->sourceFileName);
        self::assertIsArray($size);
        self::assertSame(4, $size[0], '180° must not swap dimensions');
        self::assertSame(2, $size[1]);
        self::assertNotSame($before, $media->getHash(), 'the master is still re-encoded');
    }

    public function testRotateRejectsNonMultipleOf90(): void
    {
        $rotator = $this->createRotator();
        $media = $this->createSourceMedia(4, 2);

        $this->expectException(InvalidArgumentException::class);
        $rotator->rotate($media, 45);
    }

    public function testRotateRejectsNonImage(): void
    {
        $rotator = $this->createRotator();
        $media = new Media();
        $media->setFileName('document.pdf')->setMimeType('application/pdf');

        $this->expectException(InvalidArgumentException::class);
        $rotator->rotate($media, 90);
    }

    private function createRotator(): ImageRotator
    {
        $imageReader = new ImageReader($this->mediaStorage);
        $imageEncoder = new ImageEncoder();
        $filters = ['md' => ['quality' => 80, 'filters' => ['scaleDown' => [300]]]];
        $imageCacheManager = new ImageCacheManager($filters, $this->tmpPublicDir, 'media', $this->mediaStorage);

        $dispatcher = self::getContainer()->get(BackgroundTaskDispatcherInterface::class);
        $imageCacheGenerator = new ImageCacheGenerator($imageReader, $imageEncoder, $imageCacheManager, $dispatcher, $this->mediaStorage);

        return new ImageRotator($imageReader, $imageEncoder, $imageCacheManager, $imageCacheGenerator, $this->mediaStorage);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function createSourceMedia(int $width, int $height): Media
    {
        $this->sourceFileName = 'zz-rotate-'.getmypid().'-'.uniqid().'.jpg';
        $mediaDir = $this->mediaStorage->getMediaDir();
        new Filesystem()->mkdir($mediaDir);

        $gd = imagecreatetruecolor($width, $height);
        self::assertNotFalse($gd);
        imagejpeg($gd, $mediaDir.'/'.$this->sourceFileName);

        $hash = sha1_file($mediaDir.'/'.$this->sourceFileName, true);
        self::assertNotFalse($hash);

        $media = new Media();
        $media->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn($mediaDir)
            ->setFileName($this->sourceFileName)
            ->setAlt('rotation fixture')
            ->setMimeType('image/jpeg')
            ->setHash($hash);

        return $media;
    }
}
