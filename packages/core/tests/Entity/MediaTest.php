<?php

namespace Pushword\Core\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;

class MediaTest extends TestCase
{
    public function testBasic(): void
    {
        $media = new Media();
        self::assertEmpty($media->getName());

        $media->setName('test');
        self::assertSame('test', $media->getName());
    }

    public function testLoad(): void
    {
        // Default is the liip filter
        $src = '/media/default/test.jpg';
        $media = Media::loadFromSrc($src);

        self::assertStringNotContainsString('media/default', $media->getMedia());
        self::assertSame('test', $media->getSlug());
    }
}
