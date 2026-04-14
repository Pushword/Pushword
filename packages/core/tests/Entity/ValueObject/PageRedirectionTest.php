<?php

namespace Pushword\Core\Tests\Entity\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\ValueObject\PageRedirection;

class PageRedirectionTest extends TestCase
{
    #[DataProvider('provideValidRedirections')]
    public function testFromContentReturnsRedirection(string $content, string $expectedUrl, int $expectedCode): void
    {
        $result = PageRedirection::fromContent($content);

        self::assertNotNull($result);
        self::assertSame($expectedUrl, $result->url);
        self::assertSame($expectedCode, $result->code);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function provideValidRedirections(): iterable
    {
        yield 'absolute URL default code' => ['Location:https://example.com', 'https://example.com', 301];
        yield 'absolute URL with spaces' => ['Location: https://example.com', 'https://example.com', 301];
        yield 'custom HTTP code 302' => ['Location:https://example.com 302', 'https://example.com', 302];
        yield 'relative path' => ['Location:/other-page', '/other-page', 301];
        yield 'relative path with code' => ['Location:/other-page 307', '/other-page', 307];
        yield 'code 199 matches regex' => ['Location:https://example.com 199', 'https://example.com', 199];
        yield 'code 500' => ['Location:https://example.com 500', 'https://example.com', 500];
        yield 'URL with query string' => ['Location:https://example.com/path?foo=bar', 'https://example.com/path?foo=bar', 301];
        yield 'URL with fragment' => ['Location:https://example.com/path#section', 'https://example.com/path#section', 301];
    }

    #[DataProvider('provideNonRedirections')]
    public function testFromContentReturnsNull(string $content): void
    {
        self::assertNull(PageRedirection::fromContent($content));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNonRedirections(): iterable
    {
        yield 'regular content' => ['This is a normal page content'];
        yield 'empty string' => [''];
        yield 'URL with spaces in path' => ['Location:invalid path with spaces'];
        yield 'lowercase location prefix' => ['location:https://example.com'];
        yield 'only whitespace after Location:' => ['Location:   '];
    }

    public function testConstructor(): void
    {
        $redirection = new PageRedirection('https://example.com', 302);

        self::assertSame('https://example.com', $redirection->url);
        self::assertSame(302, $redirection->code);
    }

    public function testConstructorDefaultCode(): void
    {
        $redirection = new PageRedirection('https://example.com');

        self::assertSame(301, $redirection->code);
    }
}
