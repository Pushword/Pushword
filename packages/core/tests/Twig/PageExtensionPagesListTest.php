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
        $page->setSlug('pages-list-context');
        $page->setName('Context');

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
}
