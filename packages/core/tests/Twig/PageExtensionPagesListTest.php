<?php

namespace Pushword\Core\Tests\Twig;

use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\PageExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageExtensionPagesListTest extends KernelTestCase
{
    private function ext(): PageExtension
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');

        return self::getContainer()->get(PageExtension::class);
    }

    /** A transient page used only as the rendering context (host + self-exclusion). */
    private function currentPage(): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'pages-list-context';
        $page->name = 'Context';

        return $page;
    }

    /**
     * Regression: pages_list('slug:…') with no explicit max used to throw a bare
     * LogicException, which surfaced as a 500 on the rendered content block.
     * Omitting max now means "no limit" (the repository already treats 0 as unlimited),
     * so the matching pages are listed instead of erroring.
     */
    public function testRenderPagesListWithoutMaxDoesNotThrowAndListsMatches(): void
    {
        $ext = $this->ext();

        // No-max call must not throw (the regression) and a matching slug must render more
        // than the empty list rendered for a missing slug, proving the query actually ran.
        $matched = $ext->renderPagesList('slug:homepage', currentPage: $this->currentPage());
        $empty = $ext->renderPagesList('slug:this-slug-does-not-exist-xyz', currentPage: $this->currentPage());
        self::assertNotSame($empty, $matched);
    }

    /** Omitting max (0 = no limit) renders the same output as a generous explicit max. */
    public function testRenderPagesListWithoutMaxMatchesExplicitMax(): void
    {
        $ext = $this->ext();

        self::assertSame(
            $ext->renderPagesList('slug:homepage', 10, currentPage: $this->currentPage()),
            $ext->renderPagesList('slug:homepage', currentPage: $this->currentPage()),
        );
    }

    /** Pagination still needs a positive per-page count: max < 1 with maxPages > 1 must fail loudly. */
    public function testRenderPagesListStillGuardsPaginationWithoutPerPageCount(): void
    {
        $this->expectException(LogicException::class);

        $this->ext()->renderPagesList('slug:homepage', maxPages: 2, currentPage: $this->currentPage());
    }

    /**
     * The `horizontalScroll` view renders the same cards as `card`, wrapped in the
     * CSS-only scroller. Without the mapping the view string falls through as a
     * template path and the render blows up, so this pins the mapping itself.
     */
    public function testRenderPagesListHorizontalScrollViewRendersTheScroller(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'horizontalScroll',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('horizontal-scroll-wrap', $rendered);
        self::assertStringContainsString('class="horizontal-scroll"', $rendered);
    }

    /**
     * The arrows are `::scroll-button()` pseudo-elements, which take no aria-label:
     * their accessible name comes from the custom properties the template sets, so a
     * missing one means unlabelled buttons rather than a visible failure.
     */
    public function testRenderPagesListHorizontalScrollCarriesTranslatedArrowLabels(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'horizontalScroll',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('--horizontal-scroll-previous:', $rendered);
        self::assertStringContainsString('--horizontal-scroll-next:', $rendered);
    }

    /**
     * Regression guard for the shared card list. `itemClass` was added to
     * cardList.html.twig for the scroller; its default is what every existing card
     * grid renders with, and losing it would silently restyle all of them.
     */
    public function testRenderPagesListCardViewKeepsItsGridItemClasses(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'card',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('class="w-full px-1 my-1 sm:w-1/2 md:w-1/3"', $rendered);
        self::assertStringNotContainsString('horizontal-scroll', $rendered);
    }

    /** The scroller needs fixed-width items, which is the whole point of `itemClass`. */
    public function testRenderPagesListHorizontalScrollOverridesTheGridItemClasses(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'horizontalScroll',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('class="w-72 sm:w-80"', $rendered);
        self::assertStringNotContainsString('sm:w-1/2', $rendered);
    }

    /** An empty scroller must not render a wrapper with arrows around nothing. */
    public function testRenderPagesListHorizontalScrollRendersNoScrollerWhenEmpty(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:this-slug-does-not-exist-xyz',
            10,
            view: 'horizontalScroll',
            currentPage: $this->currentPage(),
        );

        self::assertStringNotContainsString('horizontal-scroll', $rendered);
    }

    /** The wrapperClass tune must survive next to the class the scroller needs. */
    public function testRenderPagesListHorizontalScrollKeepsWrapperClass(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'horizontalScroll',
            wrapperClass: 'bg-pink-50',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('class="horizontal-scroll bg-pink-50"', $rendered);
    }
}
