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
}
