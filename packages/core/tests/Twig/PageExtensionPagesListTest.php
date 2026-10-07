<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Twig;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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

    /**
     * A curated row: `order: 'search'` renders the pages in the order their slugs are
     * written, which no column can express. The default order is asserted alongside,
     * or the expected sequence could be the one a date sort produces anyway.
     */
    public function testOrderSearchKeepsTheSlugsInTheOrderWritten(): void
    {
        $search = 'slug:demo-scroller/rocher-rond'
            .' OR slug:demo-scroller/tour-du-mont-blanc'
            .' OR slug:demo-scroller/canyoning-champsaur';

        $curated = $this->ext()->renderPagesList($search, 10, order: 'search', view: 'card', currentPage: $this->currentPage());
        $byDate = $this->ext()->renderPagesList($search, 10, view: 'card', currentPage: $this->currentPage());

        self::assertLessThan(mb_strpos($curated, 'Tour du Mont-Blanc'), mb_strpos($curated, 'Rocher Rond'));
        self::assertLessThan(mb_strpos($curated, 'Canyoning in the Champsaur'), mb_strpos($curated, 'Tour du Mont-Blanc'));

        self::assertLessThan(mb_strpos($byDate, 'Rocher Rond'), mb_strpos($byDate, 'Tour du Mont-Blanc'));
    }

    /**
     * The cut has to wait for the curated order: limiting in SQL would keep the two
     * most recent pages instead of the two written first.
     */
    public function testOrderSearchAppliesMaxAfterReordering(): void
    {
        $rendered = $this->ext()->renderPagesList(
            'slug:demo-scroller/rocher-rond OR slug:demo-scroller/tour-du-mont-blanc OR slug:demo-scroller/canyoning-champsaur',
            2,
            order: 'search',
            view: 'card',
            currentPage: $this->currentPage(),
        );

        self::assertStringContainsString('Rocher Rond', $rendered);
        self::assertStringContainsString('Tour du Mont-Blanc', $rendered);
        self::assertStringNotContainsString('Canyoning in the Champsaur', $rendered);
    }

    /**
     * `slug:%pattern%` matches more than one page, so it holds no single position:
     * ordering by the search then has nothing to order and the default applies,
     * rather than the render failing on an `order` no column answers to.
     */
    public function testOrderSearchFallsBackToTheDefaultOrderWhenNoSlugIsNamed(): void
    {
        self::assertSame(
            $this->ext()->renderPagesList('slug:%demo-scroller%', 3, view: 'card', currentPage: $this->currentPage()),
            $this->ext()->renderPagesList('slug:%demo-scroller%', 3, order: 'search', view: 'card', currentPage: $this->currentPage()),
        );
    }

    /**
     * A search may name a few pages and match others by another term: the named ones
     * lead, in the order written, and the rest follow — which is what makes
     * `slug:… OR tag` a usable way to pin two cards at the head of a tag list.
     */
    public function testOrderSearchPinsTheNamedSlugsAheadOfWhatOtherTermsMatch(): void
    {
        $slugs = $this->slugs('slug:demo-scroller/rocher-rond OR mountain-lodge', 'search');

        self::assertSame('demo-scroller/rocher-rond', $slugs[0]);
        self::assertGreaterThan(1, \count($slugs), 'the tag term must bring its own pages in');
        self::assertNotContains('demo-scroller/rocher-rond', \array_slice($slugs, 1), 'a named page appears once');
    }

    /**
     * `search` composes: it takes the head of the expression and whatever follows
     * orders everything it does not name. Asserted by reversing that tail — the
     * demo pages are published a day apart, so their order is total.
     */
    public function testOrderSearchComposesWithAColumnForTheRest(): void
    {
        $search = 'slug:demo-scroller/canyoning-champsaur OR slug:%demo-scroller%';

        $ascending = $this->slugs($search, 'search, publishedAt ↑');
        $descending = $this->slugs($search, 'search, publishedAt ↓');

        self::assertSame('demo-scroller/canyoning-champsaur', $ascending[0]);
        self::assertSame('demo-scroller/canyoning-champsaur', $descending[0]);
        self::assertSame(array_reverse(\array_slice($ascending, 1)), \array_slice($descending, 1));
    }

    /**
     * `pages()` cuts on its own path, after the reordering as pages_list() does —
     * a limit left to the query would return the two most recent instead.
     */
    public function testOrderSearchAppliesPagesMaxAfterReordering(): void
    {
        $pages = $this->ext()->getPublishedPages(
            'localhost.dev',
            'slug:demo-scroller/rocher-rond OR slug:demo-scroller/tour-du-mont-blanc OR slug:demo-scroller/canyoning-champsaur',
            'search',
            2,
        );

        self::assertSame(
            ['demo-scroller/rocher-rond', 'demo-scroller/tour-du-mont-blanc'],
            array_map(static fn (Page $page): string => $page->slug, $pages),
        );
    }

    /**
     * @return string[]
     */
    private function slugs(string $search, string $order): array
    {
        return array_map(
            static fn (Page $page): string => $page->slug,
            $this->ext()->getPublishedPages('localhost.dev', $search, $order),
        );
    }

    /**
     * `pages()` returns entities a template arranges itself, which is where a curated
     * order matters most — and it normalises `order` the same way, so leaving it out
     * would send `search` to the query as a column name.
     */
    public function testOrderSearchAlsoOrdersTheEntitiesPagesReturns(): void
    {
        $pages = $this->ext()->getPublishedPages(
            'localhost.dev',
            'slug:demo-scroller/rocher-rond OR slug:demo-scroller/tour-du-mont-blanc',
            'search',
        );

        self::assertSame(
            ['demo-scroller/rocher-rond', 'demo-scroller/tour-du-mont-blanc'],
            array_map(static fn (Page $page): string => $page->slug, $pages),
        );
    }

    /**
     * A `prop:` term names pages as well as a slug does — a product code outlives a
     * renamed slug — so `search` follows those terms too. The pages are published a
     * day apart and written in neither date order, so no column produces the result.
     */
    public function testOrderSearchKeepsThePropertyTermsInTheOrderWritten(): void
    {
        $this->withTripPages(['a' => 'TRIP-a', 'b' => 'TRIP-b', 'c' => 'TRIP-c'], function (): void {
            self::assertSame(
                ['search-order-prop-b', 'search-order-prop-c', 'search-order-prop-a'],
                $this->slugs('prop:tripCode:TRIP-b OR prop:tripCode:TRIP-c OR prop:tripCode:TRIP-a', 'search'),
            );
        });
    }

    /**
     * `customProperty:` is the older spelling of `prop:`, and the one a
     * PagesListSearchEvent listener may still write: it must order the same way.
     */
    public function testOrderSearchFollowsTheOlderCustomPropertySpelling(): void
    {
        $this->withTripPages(['a' => 'TRIP-a', 'b' => 'TRIP-b', 'c' => 'TRIP-c'], function (): void {
            self::assertSame(
                ['search-order-prop-b', 'search-order-prop-c', 'search-order-prop-a'],
                $this->slugs('customProperty:tripCode:TRIP-b OR customProperty:tripCode:TRIP-c OR customProperty:tripCode:TRIP-a', 'search'),
            );
        });
    }

    /**
     * Slug and property terms share one sequence: a page takes the position of the
     * term naming it, whichever kind it is. c, a, b is neither date order.
     */
    public function testOrderSearchInterleavesSlugAndPropertyTermsAsWritten(): void
    {
        $this->withTripPages(['a' => 'TRIP-a', 'b' => 'TRIP-b', 'c' => 'TRIP-c'], function (): void {
            self::assertSame(
                ['search-order-prop-c', 'search-order-prop-a', 'search-order-prop-b'],
                $this->slugs('prop:tripCode:TRIP-c OR slug:search-order-prop-a OR prop:tripCode:TRIP-b', 'search'),
            );
        });
    }

    /**
     * A property value may name several pages: they share its position, and the
     * column after `search` orders them among themselves — asserted both ways.
     */
    public function testOrderSearchGivesPagesSharingAPropertyValueOnePosition(): void
    {
        $this->withTripPages(['a' => 'TRIP-shared', 'b' => 'TRIP-shared', 'c' => 'TRIP-c'], function (): void {
            $search = 'prop:tripCode:TRIP-c OR prop:tripCode:TRIP-shared';

            self::assertSame(
                ['search-order-prop-c', 'search-order-prop-a', 'search-order-prop-b'],
                $this->slugs($search, 'search, publishedAt ↑'),
            );
            self::assertSame(
                ['search-order-prop-c', 'search-order-prop-b', 'search-order-prop-a'],
                $this->slugs($search, 'search, publishedAt ↓'),
            );
        });
    }

    /**
     * A page is named by the value of its property, not by carrying it: pages with
     * another `tripCode`, matched by another term, follow the named one.
     */
    public function testOrderSearchRanksByThePropertyValueNotItsPresence(): void
    {
        $this->withTripPages(['a' => 'TRIP-a', 'b' => 'TRIP-b', 'c' => 'TRIP-c'], function (): void {
            self::assertSame(
                ['search-order-prop-b', 'search-order-prop-a', 'search-order-prop-c'],
                $this->slugs('prop:tripCode:TRIP-b OR slug:%search-order-prop%', 'search, publishedAt ↑'),
            );
        });
    }

    /**
     * pages_list() reaches the ordering on its own path: property terms must order
     * the rendered cards too, and `max` cut after that order, not the dates.
     */
    public function testOrderSearchOrdersThePropertyTermsPagesListRenders(): void
    {
        $this->withTripPages(['a' => 'TRIP-a', 'b' => 'TRIP-b', 'c' => 'TRIP-c'], function (): void {
            $rendered = $this->ext()->renderPagesList(
                'prop:tripCode:TRIP-b OR prop:tripCode:TRIP-c OR prop:tripCode:TRIP-a',
                2,
                order: 'search',
                view: 'card',
                currentPage: $this->currentPage(),
            );

            self::assertStringContainsString('Search order b', $rendered);
            self::assertLessThan(mb_strpos($rendered, 'Search order c'), mb_strpos($rendered, 'Search order b'));
            self::assertStringNotContainsString('Search order a', $rendered);
        });
    }

    /**
     * Persists one published page per entry, runs $test, and removes the pages
     * whatever it found. They are published a day apart in the order given — the
     * first the oldest — so any date order is total.
     *
     * @param array<string, scalar> $tripCodes slug suffix => `tripCode` property
     */
    private function withTripPages(array $tripCodes, callable $test): void
    {
        $this->ext();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $pages = [];
        $daysAgo = \count($tripCodes);
        foreach ($tripCodes as $suffix => $tripCode) {
            $page = new Page();
            $page->host = 'localhost.dev';
            $page->locale = 'en';
            $page->slug = 'search-order-prop-'.$suffix;
            $page->h1 = 'Search order '.$suffix;
            $page->mainContent = 'Content';
            $page->publishedAt = new DateTime($daysAgo.' days ago');
            $page->setCustomProperty('tripCode', $tripCode);
            $entityManager->persist($page);
            $pages[] = $page;
            --$daysAgo;
        }

        $entityManager->flush();

        try {
            $test();
        } finally {
            // $test boots a fresh kernel, whose entity manager has never seen these pages.
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            foreach ($pages as $page) {
                $managed = $entityManager->find(Page::class, $page->id);
                if (null !== $managed) {
                    $entityManager->remove($managed);
                }
            }

            $entityManager->flush();
        }
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
