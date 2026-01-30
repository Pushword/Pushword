<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\IsAssociativeArray;

class IsAssociativeArrayTest extends TestCase
{
    /**
     * @param array<mixed> $input
     */
    #[DataProvider('provideArrays')]
    public function testIsAssociative(array $input, bool $expected): void
    {
        self::assertSame($expected, IsAssociativeArray::test($input));
    }

    /**
     * @return iterable<string, array{array<mixed>, bool}>
     */
    public static function provideArrays(): iterable
    {
        yield 'empty array' => [[], false];
        yield 'sequential' => [[0, 1, 2], false];
        yield 'associative' => [['a' => 1, 'b' => 2], true];
        yield 'mixed keys' => [[0 => 'a', 'key' => 'b'], true];
        yield 'single sequential' => [['value'], false];
        yield 'single associative' => [['key' => 'value'], true];
    }
}
