<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\ShowMore;

use function Safe\preg_match;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ShowMoreTest extends KernelTestCase
{
    public function testStartAndEndWrapTheContentInACheckboxToggle(): void
    {
        $showMore = $this->getShowMore();

        $before = $showMore->startShowMore();
        $after = $showMore->endShowMore();

        self::assertStringContainsString('<input type="checkbox"', $before);
        self::assertStringContainsString('class="show-more ', $before);
        self::assertStringContainsString('window.ShowMore.open(this)', $after);
        self::assertStringContainsString('window.ShowMore.close(this)', $after);
    }

    public function testAuthorProvidedIdIsUsedVerbatim(): void
    {
        self::assertStringContainsString('id="my-section"', $this->getShowMore()->startShowMore('my-section'));
        // The collapsible body is derived from it, so the pair stays addressable.
        self::assertStringContainsString('id="csm_my-section"', $this->getShowMore()->startShowMore('my-section'));
    }

    public function testExtraClassAndBackgroundReachTheTemplate(): void
    {
        $showMore = $this->getShowMore();

        self::assertStringContainsString('class="show-more mt-8"', $showMore->startShowMore(null, 'mt-8'));
        self::assertStringContainsString('via-gray-100 to-gray-100', $showMore->endShowMore('via-gray-100 to-gray-100'));
    }

    public function testDefaultBackgroundIsWhite(): void
    {
        self::assertStringContainsString('via-white to-white', $this->getShowMore()->endShowMore());
    }

    public function testTwoBlocksOnOnePageGetDistinctIds(): void
    {
        $showMore = $this->getShowMore();

        self::assertNotSame($this->idOf($showMore->startShowMore()), $this->idOf($showMore->startShowMore()));
    }

    /**
     * Ids derive from the page slug, not from `uniqid()`: re-rendering an unchanged
     * page has to produce identical bytes (static builds skip rewrites on that).
     */
    public function testIdsAreDeterministicForAGivenPage(): void
    {
        $first = $this->idOf($this->getShowMore(new Page())->startShowMore());

        self::ensureKernelShutdown();
        $second = $this->idOf($this->getShowMore(new Page())->startShowMore());

        self::assertNotSame('', $first);
        self::assertSame($first, $second);
    }

    /**
     * Worker-mode equivalent of the test above: between two requests the kernel is
     * not shut down, only reset. Without it the second page served kept numbering
     * where the first stopped.
     */
    public function testResetRestartsTheNumbering(): void
    {
        $showMore = $this->getShowMore(new Page());

        $first = $this->idOf($showMore->startShowMore());
        $showMore->endShowMore();
        $showMore->reset();

        self::assertSame($first, $this->idOf($showMore->startShowMore()));
    }

    /**
     * One process rendering many pages (static build, page scan) never resets in
     * between: the page each block belongs to has to restart the numbering.
     */
    public function testEachPageGetsTheIdsItWouldGetOnItsOwn(): void
    {
        $showMore = $this->getShowMore();

        $onFirstPage = $this->idOf($showMore->renderStart(null, '', $this->page('first')));
        $showMore->renderEnd(null, null, null);
        $afterAnotherPage = $this->idOf($showMore->renderStart(null, '', $this->page('second')));

        $showMore->reset();
        $alone = $this->idOf($showMore->renderStart(null, '', $this->page('second')));

        self::assertSame($alone, $afterAnotherPage);
        self::assertNotSame($onFirstPage, $afterAnotherPage);
    }

    /**
     * A body filtered with no page in sight — an excerpt, an API render. Restarting
     * the numbering there would hand the second block the first one's id, since the
     * filter and the Twig calls of one body share this counter.
     */
    public function testARenderWithNoPageCarriesOnNumbering(): void
    {
        $showMore = $this->getShowMore();

        $first = $this->idOf($showMore->renderStart(null, '', null));
        $showMore->renderEnd(null, null, null);
        $second = $this->idOf($showMore->renderStart(null, '', null));

        self::assertNotSame('', $first);
        self::assertNotSame($first, $second);
    }

    private function page(string $slug): Page
    {
        $page = new Page(false);
        $page->slug = $slug;
        $page->host = 'localhost';

        return $page;
    }

    private function getShowMore(?Page $currentPage = null): ShowMore
    {
        self::bootKernel();

        if (null !== $currentPage) {
            $currentPage->slug = 'a-stable-slug';
            self::getContainer()->get(SiteRegistry::class)->setCurrentPage($currentPage);
        }

        return self::getContainer()->get(ShowMore::class);
    }

    private function idOf(string $html): string
    {
        preg_match('/<input type="checkbox" id="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }
}
