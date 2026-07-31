<?php

namespace Pushword\Core\Tests\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Query\LikePattern;

/**
 * The escaping every value-carrying LIKE depends on.
 *
 * Its failures are silent by nature — an unescaped `_` widens a match instead of
 * erroring — so the cases are pinned here rather than left to the queries that
 * happen to exercise them.
 */
final class LikePatternTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function values(): iterable
    {
        yield 'an ordinary value is untouched' => ['AmTrek', 'AmTrek'];

        yield 'an underscore is a wildcard and a legal character' => ['AmTrek_2026', 'AmTrek!_2026'];

        yield 'so is a percent' => ['100%Trek', '100!%Trek'];

        // Without this the escape character would be a way back out of the
        // escaping: `AmTrek!_x` would arrive as a pattern meaning `AmTrek` + any
        // character + `x`.
        yield 'the escape character escapes itself' => ['AmTrek!_x', 'AmTrek!!!_x'];

        yield 'every occurrence, not just the first' => ['a_b_c', 'a!_b!_c'];

        yield 'an empty value stays empty' => ['', ''];
    }

    #[DataProvider('values')]
    public function testEscape(string $value, string $escaped): void
    {
        self::assertSame($escaped, LikePattern::escape($value));
    }

    /**
     * The escape clause travels with the comparison. Written apart, one of them
     * eventually ships without the other — and then the pattern means nothing on
     * SQLite, which gives LIKE no escape character unless one is named.
     */
    public function testComparisonCarriesTheEscapeClause(): void
    {
        self::assertSame("p.tags LIKE :w0 ESCAPE '!'", LikePattern::comparison('p.tags', 'LIKE', 'w0'));
        self::assertSame("c.tags NOT LIKE :seg1 ESCAPE '!'", LikePattern::comparison('c.tags', 'NOT LIKE', 'seg1'));
    }
}
