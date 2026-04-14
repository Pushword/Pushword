<?php

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\PageScanner\Scanner\RegexHelper;

class RegexHelperTest extends TestCase
{
    #[DataProvider('provideStringEscaping')]
    public function testPrepareForRegexWithString(string $input, string $expected): void
    {
        self::assertSame($expected, RegexHelper::prepareForRegex($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideStringEscaping(): iterable
    {
        yield 'plain string' => ['hello', 'hello'];
        yield 'dot' => ['file.txt', 'file\.txt'];
        yield 'slash' => ['path/to', 'path\/to'];
        yield 'special chars' => ['foo(bar)', 'foo\(bar\)'];
        yield 'question mark' => ['search?q=1', 'search\?q\=1'];
        yield 'brackets' => ['arr[0]', 'arr\[0\]'];
        yield 'asterisk' => ['*.txt', '\*\.txt'];
    }

    public function testPrepareForRegexWithArray(): void
    {
        $result = RegexHelper::prepareForRegex(['foo', 'bar.baz']);

        self::assertSame('(foo|bar\.baz)', $result);
    }

    public function testPrepareForRegexWithSingleElementArray(): void
    {
        $result = RegexHelper::prepareForRegex(['hello']);

        self::assertSame('(hello)', $result);
    }
}
