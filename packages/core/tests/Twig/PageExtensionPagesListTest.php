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
     * The scroller shares cardList.html.twig with the `card` view, so its own layout
     * must not leak there. Deliberately says nothing about what the card grid *does*
     * render: those classes belong to that template and are free to change.
     */
    public function testRenderPagesListCardViewIsUnaffectedByTheScroller(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'card',
            currentPage: $this->currentPage(),
        );

        self::assertStringNotContainsString('horizontal-scroll', $rendered);
        self::assertStringNotContainsString('w-72', $rendered);
    }

    /** The scroller needs fixed-width items, which is the whole point of `itemClass`. */
    public function testRenderPagesListHorizontalScrollGivesItemsTheScrollerWidth(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'horizontalScroll',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('class="w-72 sm:w-80"', $rendered);
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

    /**
     * wrapperClass lands on the positioned wrapper, not on the scrolling <ul>. The
     * arrows are absolutely positioned against that wrapper, so a layout class on the
     * <ul> — `bleed` being the obvious one — would widen the row and leave the arrows
     * pinned to the narrow box. The scroller keeps its own class untouched.
     */
    public function testRenderPagesListHorizontalScrollPutsWrapperClassOnThePositionedWrapper(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'horizontalScroll',
            wrapperClass: 'bleed',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('class="horizontal-scroll-wrap not-prose my-5 bleed"', $rendered);
        self::assertStringContainsString('class="horizontal-scroll"', $rendered);
    }

    /**
     * The card list's columns live on the wrapper alone (grid), so a single class
     * string controls the whole layout; items must carry no width class of their
     * own, or a wrapperClass could never change the column count.
     */
    public function testRenderPagesListCardViewPutsTheColumnsOnTheWrapperOnly(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'card',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('grid-cols', $rendered);
        self::assertDoesNotMatchRegularExpression('/<li[^>]*class=/', $rendered);
    }

    /** A wrapperClass replaces the default layout entirely — the customization seam. */
    public function testRenderPagesListCardViewWrapperClassReplacesTheLayout(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'card',
            wrapperClass: 'grid gap-4 md:grid-cols-4',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('class="grid gap-4 md:grid-cols-4"', $rendered);
        self::assertStringNotContainsString('md:grid-cols-3', $rendered);
    }

    /**
     * A bare view name is a site display variant by convention: it must resolve to
     * /component/pages_list_<name>.html.twig through the site's template dirs
     * (fixture in dev-app's templates/component/).
     */
    public function testRenderPagesListBareViewNameResolvesToASiteVariantTemplate(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:homepage',
            10,
            view: 'testVariant',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('data-pages-list-variant="testVariant"', $rendered);
    }

    /**
     * A view spelling a path (what a theme's own template needs) must keep passing
     * through untouched — the bare-name arm must not swallow it into
     * /component/pages_list_<path>.html.twig.
     */
    public function testRenderPagesListViewPathStillPassesThrough(): void
    {
        $ext = $this->ext();

        self::assertSame(
            $ext->renderPagesList('slug:homepage', 10, view: 'list', currentPage: $this->currentPage()),
            $ext->renderPagesList('slug:homepage', 10, view: '/component/pages_list.html.twig', currentPage: $this->currentPage()),
        );
    }
}
