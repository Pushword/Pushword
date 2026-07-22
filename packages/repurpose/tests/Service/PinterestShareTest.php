<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\PinterestShare;

#[Group('integration')]
final class PinterestShareTest extends TestCase
{
    public function testBuildsTheCreatePinUrlWithMediaPageAndDescription(): void
    {
        $url = new PinterestShare()->pinUrl('https://ex.com/repurpose-pin/7.png', 'https://ex.com/blog/mon-article', 'Look at this');

        self::assertStringStartsWith('https://www.pinterest.com/pin/create/button/?', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame('https://ex.com/repurpose-pin/7.png', $query['media']);
        self::assertSame('https://ex.com/blog/mon-article', $query['url']);
        self::assertSame('Look at this', $query['description']);
    }

    public function testOmitsThePageUrlAndDescriptionWhenAbsent(): void
    {
        $url = new PinterestShare()->pinUrl('https://ex.com/repurpose-pin/7.png', null, null);

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertArrayHasKey('media', $query);
        self::assertArrayNotHasKey('url', $query);
        self::assertArrayNotHasKey('description', $query);
    }

    public function testCapsTheDescriptionAtPinterestsLimit(): void
    {
        $long = str_repeat('a', 800);
        $url = new PinterestShare()->pinUrl('https://ex.com/p.png', null, $long);

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertIsString($query['description']);
        self::assertSame(500, mb_strlen($query['description']));
    }
}
