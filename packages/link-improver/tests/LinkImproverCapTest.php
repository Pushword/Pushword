<?php

namespace Pushword\LinkImprover\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\LinkImprover\LinkImprover;

/**
 * The number the panel reports as the cap has to be the number the engine
 * actually stops at — it stops while it still has a whole link of room, so the
 * reachable total is the floor of the allowance, not the allowance.
 */
final class LinkImproverCapTest extends TestCase
{
    /**
     * @return iterable<string, array{float, int, int}>
     */
    public static function caps(): iterable
    {
        // Below 1 the setting is a ratio of the word count.
        yield 'the default ratio over a real page' => [0.02, 312, 6];
        yield 'a ratio that lands exactly on an integer' => [0.02, 300, 6];
        yield 'a ratio rounding down, never up' => [0.02, 349, 6];
        yield 'a page too short to earn a single link' => [0.02, 10, 0];
        yield 'an empty page' => [0.02, 0, 0];

        // At 1 and above it is an absolute count, whatever the length.
        yield 'an absolute cap ignores the word count' => [5.0, 312, 5];
        yield 'an absolute cap on a short page' => [5.0, 3, 5];
        yield 'one is already absolute, not a ratio' => [1.0, 1000, 1];
    }

    #[DataProvider('caps')]
    public function testTheCapIsTheTotalThePageMayEndWith(float $maxLinks, int $wordCount, int $expected): void
    {
        self::assertSame($expected, LinkImprover::cap($maxLinks, $wordCount));
    }
}
