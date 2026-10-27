<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Entity\Audience;

final class AudienceStringTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, string}> */
    public static function labels(): iterable
    {
        yield 'default sender name and host' => [
            'Newsletter',
            '',
            'newsletter.example',
            'Newsletter (newsletter.example)',
        ];
        yield 'distinct brand and subscription name' => [
            'Newsletter',
            'Grand Angle',
            'www.grandangle.fr',
            'Grand Angle · Newsletter (www.grandangle.fr)',
        ];
        yield 'duplicate sender name and empty host' => [
            'Newsletter',
            'Newsletter',
            '',
            'Newsletter',
        ];
        yield 'slug fallback with empty host' => ['', '', '', 'newsletter-fr'];
    }

    #[DataProvider('labels')]
    public function testItIdentifiesTheAudience(string $name, string $fromName, string $mainHost, string $expected): void
    {
        $audience = new Audience();
        $audience->name = $name;
        $audience->slug = 'newsletter-fr';
        $audience->fromName = $fromName;
        $audience->mainHost = $mainHost;

        self::assertSame($expected, (string) $audience);
    }
}
