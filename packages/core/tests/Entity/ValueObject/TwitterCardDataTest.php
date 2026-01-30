<?php

namespace Pushword\Core\Tests\Entity\ValueObject;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\ValueObject\TwitterCardData;

class TwitterCardDataTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $data = new TwitterCardData();
        self::assertTrue($data->isEmpty());
        self::assertNull($data->card);
        self::assertNull($data->site);
        self::assertNull($data->creator);
    }

    public function testNotEmptyWithCard(): void
    {
        $data = new TwitterCardData(card: 'summary');
        self::assertFalse($data->isEmpty());
        self::assertSame('summary', $data->card);
    }

    public function testNotEmptyWithSite(): void
    {
        $data = new TwitterCardData(site: '@example');
        self::assertFalse($data->isEmpty());
    }

    public function testNotEmptyWithCreator(): void
    {
        $data = new TwitterCardData(creator: '@author');
        self::assertFalse($data->isEmpty());
    }

    public function testFullyPopulated(): void
    {
        $data = new TwitterCardData(
            card: 'summary_large_image',
            site: '@example',
            creator: '@author',
        );
        self::assertFalse($data->isEmpty());
        self::assertSame('summary_large_image', $data->card);
        self::assertSame('@example', $data->site);
        self::assertSame('@author', $data->creator);
    }
}
