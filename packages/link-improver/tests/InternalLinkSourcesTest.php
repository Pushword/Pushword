<?php

namespace Pushword\LinkImprover\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\LinkImprover\InternalLinkSources;

final class InternalLinkSourcesTest extends TestCase
{
    public function testKeywordsSplitsOnNewlines(): void
    {
        self::assertSame(
            ['Kiwano Melano', 'horned melon'],
            InternalLinkSources::keywords("Kiwano Melano\nhorned melon")
        );
    }

    public function testKeywordsSplitsOnCommasLikeTheEngineCsvFormat(): void
    {
        self::assertSame(
            ['Kiwano', 'horned melon', 'melano'],
            InternalLinkSources::keywords("Kiwano, horned melon\nmelano")
        );
    }

    public function testKeywordsTrimsAndDropsEmptyLines(): void
    {
        self::assertSame(
            ['One', 'Two'],
            InternalLinkSources::keywords("  One  \n\n Two \n")
        );
    }

    public function testKeywordsOnEmptyName(): void
    {
        self::assertSame([], InternalLinkSources::keywords(''));
    }
}
