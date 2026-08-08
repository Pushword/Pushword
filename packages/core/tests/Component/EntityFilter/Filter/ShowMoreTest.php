<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\ShowMore;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The legacy comment syntax. Every case below is one the `explode()`-based
 * implementation got wrong.
 */
#[Group('integration')]
final class ShowMoreTest extends KernelTestCase
{
    private function filter(string $body, string $slug = 'a-page'): string
    {
        self::bootKernel();

        $page = new Page(false);
        $page->slug = $slug;
        $page->host = 'localhost';

        $filtered = self::getContainer()->get(ShowMore::class)->apply(
            $body,
            $page,
            new ReflectionClass(Manager::class)->newInstanceWithoutConstructor(),
        );
        self::assertIsString($filtered);

        return $filtered;
    }

    private function occurrencesOf(string $needle, string $haystack): int
    {
        return substr_count($haystack, $needle);
    }

    public function testAPairBecomesTheCollapsibleWrapper(): void
    {
        $filtered = $this->filter("Intro\n\n<!--start-show-more-->\n\nHidden\n\n<!--end-show-more-->\n");

        self::assertStringContainsString('class="show-more ', $filtered);
        self::assertStringContainsString('window.ShowMore.open(this)', $filtered);
        // The wrapped markdown stays markdown: a blank line has to follow the
        // opening tags, or CommonMark reads it as raw HTML.
        self::assertMatchesRegularExpression('/id="csm_[^"]+">\n\n\nHidden/', $filtered);
        self::assertStringNotContainsString('<!--start-show-more-->', $filtered);
    }

    /** `explode("\n<!--start…")` needed a leading newline, so a body opening on the marker kept it. */
    public function testAMarkerOpeningTheBodyIsExpanded(): void
    {
        $filtered = $this->filter("<!--start-show-more-->\n\nHidden\n\n<!--end-show-more-->");

        self::assertStringContainsString('class="show-more ', $filtered);
        self::assertStringNotContainsString('<!--start-show-more-->', $filtered);
    }

    /** A lone closer used to wrap everything above it in a collapsed block. */
    public function testAnOrphanEndLeavesTheBodyAlone(): void
    {
        $body = "Intro\n\n<!--end-show-more-->\n\nRest";

        self::assertSame($body, $this->filter($body));
    }

    public function testAnOrphanStartLeavesTheBodyAlone(): void
    {
        $body = "Intro\n\n<!--start-show-more-->\n\nRest";

        self::assertSame($body, $this->filter($body));
    }

    /** One opening and two closings came out of the old str_replace. */
    public function testNestedPairsOpenAndCloseAsManyTimes(): void
    {
        $filtered = $this->filter(
            "<!--start-show-more-->\n\nA\n\n<!--start-show-more-->\n\nB\n\n<!--end-show-more-->\n\nC\n\n<!--end-show-more-->"
        );

        self::assertSame(2, $this->occurrencesOf('<input type="checkbox"', $filtered));
        self::assertSame(2, $this->occurrencesOf('window.ShowMore.open(this)', $filtered));
        self::assertStringNotContainsString('<!--start-show-more-->', $filtered);
        self::assertStringNotContainsString('<!--end-show-more-->', $filtered);
    }

    public function testTwoBlocksOnAPageGetDistinctIds(): void
    {
        $filtered = $this->filter(
            "<!--start-show-more-->\n\nA\n\n<!--end-show-more-->\n\n<!--start-show-more-->\n\nB\n\n<!--end-show-more-->"
        );

        preg_match_all('/<input type="checkbox" id="([^"]+)"/', $filtered, $matches);

        self::assertCount(2, $matches[1]);
        self::assertNotSame($matches[1][0], $matches[1][1]);
    }

    /** A page documenting the syntax must keep it readable. */
    public function testMarkersInAFencedBlockAreLeftAlone(): void
    {
        $body = "Intro\n\n```markdown\n<!--start-show-more-->\n\nHidden\n\n<!--end-show-more-->\n```\n\nRest";

        self::assertSame($body, $this->filter($body));
    }

    /** How an author disables a block without deleting it. */
    public function testACommentedOutMarkerIsNotAMarker(): void
    {
        $body = "Intro\n\n{# <!--start-show-more--> #}\n\nRest\n\n{# <!--end-show-more--> #}";

        self::assertSame($body, $this->filter($body));
    }
}
