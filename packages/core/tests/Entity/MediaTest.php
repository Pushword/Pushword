<?php

namespace Pushword\Core\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;

class MediaTest extends TestCase
{
    public function testBasic(): void
    {
        $media = new Media();
        self::assertEmpty($media->getAlt());

        $media->setAlt('test');
        self::assertSame('test', $media->getAlt());
    }

    public function testSetFileNameNormalizesInput(): void
    {
        $media = new Media();
        $media->setFileName('Voyage sur mesure.jpg');
        self::assertSame('voyage-sur-mesure.jpg', $media->getFileName());
    }

    public function testSetFileNamePreservesAlreadyNormalized(): void
    {
        $media = new Media();
        $media->setFileName('voyage-sur-mesure.jpg');
        self::assertSame('voyage-sur-mesure.jpg', $media->getFileName());
    }

    public function testSetFileNameHandlesAccents(): void
    {
        $media = new Media();
        $media->setFileName('été à Paris.png');
        self::assertSame('ete-a-paris.png', $media->getFileName());
    }

    public function testSetFileNameTracksHistory(): void
    {
        $media = new Media();
        $media->setFileName('first-name.jpg');
        $media->setFileName('Second Name.jpg');
        self::assertSame('second-name.jpg', $media->getFileName());
        self::assertTrue($media->hasFileNameInHistory('first-name.jpg'));
    }

    public function testSetFileNameNull(): void
    {
        $media = new Media();
        $media->setFileName('test.jpg');
        $media->setFileName(null);
        self::assertSame('test.jpg', $media->getFileName());
    }
}
