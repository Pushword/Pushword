<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\SearchNormalizer;

final class SearchNormalizerTest extends TestCase
{
    #[DataProvider('provideNormalize')]
    public function testNormalize(string $input, string $expected): void
    {
        self::assertSame($expected, SearchNormalizer::normalize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNormalize(): iterable
    {
        yield 'empty string' => ['', ''];
        yield 'already normalized' => ['hello', 'hello'];
        yield 'uppercase' => ['HELLO', 'hello'];
        yield 'accented e' => ['café', 'cafe'];
        yield 'accented mixed' => ['Résumé', 'resume'];
        yield 'german umlaut' => ['über', 'uber'];
        yield 'no diacritics needed' => ['test123', 'test123'];
    }
}
