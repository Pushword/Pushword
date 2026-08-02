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
