<?php

namespace Pushword\Core\Tests\Entity\SharedTrait;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\SharedTrait\MediaListTrait;

final class MediaListTraitTest extends TestCase
{
    public function testMediaListStartsEmpty(): void
    {
        $holder = new class {
            use MediaListTrait;
        };

        self::assertCount(0, $holder->mediaList);
    }

    public function testAssignmentCopiesAndDeduplicates(): void
    {
        $holder = new class {
            use MediaListTrait;
        };
        $media = new Media();
        $other = new Media();

        $holder->mediaList = new ArrayCollection([$media, $other, $media]);
        self::assertCount(2, $holder->mediaList);
        self::assertSame([$media, $other], $holder->mediaList->getValues());
    }

    public function testAddMediaIgnoresDuplicates(): void
    {
        $holder = new class {
            use MediaListTrait;
        };
        $media = new Media();

        $holder->addMedia($media)->addMedia($media);
        self::assertCount(1, $holder->mediaList);
    }

    public function testRemoveMedia(): void
    {
        $holder = new class {
            use MediaListTrait;
        };
        $media = new Media();

        $holder->addMedia($media);
        $holder->removeMedia($media);
        self::assertCount(0, $holder->mediaList);
    }
}
