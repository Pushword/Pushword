<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Support;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Typographer;

final class HtmlEquivalenceTest extends TestCase
{
    public function testEquivalentSerialization(): void
    {
        self::assertSame(
            HtmlEquivalence::structure('<table><tr><td class="a" id="x">l&#x27;histoire</td></tr></table>'),
            HtmlEquivalence::structure("<table>\n<tr><td id='x' class='a'>l'histoire</td></tr>\n</table>"),
        );
    }

    public function testTypographicDifferenceIsPreserved(): void
    {
        $typographer = new Typographer();

        self::assertNotSame(
            HtmlEquivalence::structure($typographer->fix("<p>l'histoire</p>", 'fr')),
            HtmlEquivalence::structure($typographer->fix('<p>l&#x27;histoire</p>', 'fr')),
        );
    }

    public function testMeaningfulDifferencesRemainVisible(): void
    {
        $baseline = HtmlEquivalence::structure('<a href="/one"><code>x</code></a>');

        self::assertNotSame($baseline, HtmlEquivalence::structure('<a href="/two"><code>x</code></a>'));
        self::assertNotSame($baseline, HtmlEquivalence::structure('<a href="/one"><code>y</code></a>'));
        self::assertNotSame($baseline, HtmlEquivalence::structure('<a href="/one"><em>x</em></a>'));
        self::assertNotSame(HtmlEquivalence::structure('<h2 id="one">Title</h2>'), HtmlEquivalence::structure('<h2 id="two">Title</h2>'));
        self::assertNotSame(HtmlEquivalence::structure('<p>two words</p>'), HtmlEquivalence::structure('<p>twowords</p>'));
        self::assertNotSame(HtmlEquivalence::structure('<!--break-->'), HtmlEquivalence::structure('<!--stop-toc-->'));
        self::assertNotSame(
            HtmlEquivalence::structure('<script>const x = "<";</script>'),
            HtmlEquivalence::structure('<script>const x = ">";</script>'),
        );
    }
}
