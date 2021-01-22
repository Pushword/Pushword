<?php

namespace Pushword\Core\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;

class MediaTest extends TestCase
{
    public function testBasic()
    {
        $media = new Media();
        $this->assertEmpty($media->getName());

        $media->setName('test');
        $this->assertSame('test', $media->getName());
    }

    public function testLoad()
    {
        // Default is the liip filter
        $src = '/media/default/test.jpg';
        $media = Media::loadFromSrc($src);

        $this->assertStringNotContainsString('media/default', $media->getMedia());
        $this->assertSame('test', $media->getSlug());
    }
}
