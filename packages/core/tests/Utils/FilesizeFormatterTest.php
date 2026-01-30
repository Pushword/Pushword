<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\FilesizeFormatter;

class FilesizeFormatterTest extends TestCase
{
    #[DataProvider('provideFormatBytes')]
    public function testFormatBytes(float|int|string $size, string $expected): void
    {
        self::assertSame($expected, FilesizeFormatter::formatBytes($size));
    }

    /**
     * @return iterable<string, array{float|int|string, string}>
     */
    public static function provideFormatBytes(): iterable
    {
        yield 'zero' => [0, '0 '];
        yield 'negative' => [-1, '0 '];
        yield 'bytes' => [500, '500 '];
        yield 'kilobytes' => [1024, '1 K'];
        yield 'kilobytes fractional' => [1536, '1.5 K'];
        yield 'megabytes' => [1048576, '1 M'];
        yield 'gigabytes' => [1073741824, '1 G'];
        yield 'string input' => ['2048', '2 K'];
        yield 'float input' => [2048.0, '2 K'];
    }
}
