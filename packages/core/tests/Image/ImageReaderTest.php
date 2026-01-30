<?php

namespace Pushword\Core\Tests\Image;

use Pushword\Core\Image\ImageReader;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

        // Test auto driver (should pick imagick if available, else gd)
        $reader = new ImageReader($mediaStorage, 'auto');
        $expectedDriver = \extension_loaded('imagick') ? 'imagick' : 'gd';
        self::assertSame($expectedDriver, $reader->getResolvedDriver());

        // Test explicit gd driver
        $reader = new ImageReader($mediaStorage, 'gd');
        self::assertSame('gd', $reader->getResolvedDriver());

        // Test explicit imagick driver (if available)
        if (\extension_loaded('imagick')) {
            $reader = new ImageReader($mediaStorage, 'imagick');
            self::assertSame('imagick', $reader->getResolvedDriver());
        }
    }
}
