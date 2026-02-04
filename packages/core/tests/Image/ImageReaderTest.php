<?php

namespace Pushword\Core\Tests\Image;

use Intervention\Image\Drivers\Vips\Driver;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

#[Group('integration')]
class ImageReaderTest extends KernelTestCase
{
    use PathTrait;

    private function createMediaStorageAdapter(): MediaStorageAdapter
    {
        self::bootKernel();

        /** @var MediaStorageAdapter */
        return self::getContainer()->get(MediaStorageAdapter::class);
    }

    public function testDriverSelection(): void
    {
        $mediaStorage = $this->createMediaStorageAdapter();

        // Test auto driver (prefers vips > imagick > gd)
        $reader = new ImageReader($mediaStorage, 'auto');
        // Mirror the same health check as ImageReader::resolveDriver()
        $vipsHealthy = false;
        if (\extension_loaded('ffi') && class_exists(Driver::class)) {
            try {
                $probeReader = new ImageReader($mediaStorage, 'auto');
                $vipsHealthy = 'vips' === $probeReader->getResolvedDriver();
            } catch (Throwable) {
            }
        }

        $expectedDriver = match (true) {
            $vipsHealthy => 'vips',
            \extension_loaded('imagick') => 'imagick',
            default => 'gd',
        };
        self::assertSame($expectedDriver, $reader->getResolvedDriver());

        // Test explicit gd driver
        $reader = new ImageReader($mediaStorage, 'gd');
        self::assertSame('gd', $reader->getResolvedDriver());

        // Test explicit imagick driver (if available)
        if (\extension_loaded('imagick')) {
            $reader = new ImageReader($mediaStorage, 'imagick');
            self::assertSame('imagick', $reader->getResolvedDriver());
        }

        // Test explicit vips driver (if available and healthy)
        if ($vipsHealthy) {
            $reader = new ImageReader($mediaStorage, 'vips');
            self::assertSame('vips', $reader->getResolvedDriver());
        }
    }

    public function testReadImage(): void
    {
        $mediaStorage = $this->createMediaStorageAdapter();
        $reader = new ImageReader($mediaStorage);

        $image = $reader->read(__DIR__.'/../Service/blank.jpg');
        self::assertSame(1, $image->width());
        self::assertSame(1, $image->height());
    }
}
