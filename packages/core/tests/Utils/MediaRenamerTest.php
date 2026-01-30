<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Utils\MediaRenamer;

class MediaRenamerTest extends TestCase
{
    public function testRenameAppendsIteration(): void
    {
        $renamer = new MediaRenamer();
        $media = new Media();
        $media->setAlt('photo');

        $renamer->rename($media);

        self::assertSame('photo (2)', $media->getAlt());
    }

    public function testRenameIncrementsIteration(): void
    {
        $renamer = new MediaRenamer();
        $media = new Media();
        $media->setAlt('photo');

        $renamer->rename($media);
        $renamer->rename($media);

        self::assertSame('photo (3)', $media->getAlt());
    }

    public function testResetResetsIteration(): void
    {
        $renamer = new MediaRenamer();
        $media = new Media();
        $media->setAlt('photo');

        $renamer->rename($media);
        self::assertSame(2, $renamer->getIteration());

        $renamer->reset();
        self::assertSame(1, $renamer->getIteration());
    }

    public function testGetIterationStartsAtOne(): void
    {
        $renamer = new MediaRenamer();

        self::assertSame(1, $renamer->getIteration());
    }
}
