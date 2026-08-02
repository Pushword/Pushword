<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\HtmlLinkMultisite;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Rewrites root-relative hrefs to whatever the router makes of the target page.
 * The homepage is the readable case: it is stored under the `homepage` slug but
 * served at `/`, so a `/homepage` link has to come out as `/`.
 */
#[Group('integration')]
final class HtmlLinkMultisiteTest extends KernelTestCase
{
    public function testHomepageLinkIsRewrittenToTheServedPath(): void
    {
        self::assertSame('<a href="/">Home</a>', $this->convert('<a href="/homepage">Home</a>'));
    }

    public function testQuotingStyleIsPreserved(): void
    {
        self::assertSame("<a href='/'>Home</a>", $this->convert("<a href='/homepage'>Home</a>"));
        self::assertSame('<a href=/>Home</a>', $this->convert('<a href=/homepage>Home</a>'));
    }

    public function testFragmentSurvivesTheRewrite(): void
    {
        self::assertSame('<a href="/#top">Top</a>', $this->convert('<a href="/homepage#top">Top</a>'));
    }

    public function testOtherAttributesAreLeftAlone(): void
    {
        self::assertSame(
            '<a class="btn" href="/" rel="nofollow">Home</a>',
            $this->convert('<a class="btn" href="/homepage" rel="nofollow">Home</a>'),
        );
    }

    public function testAbsoluteAndExternalLinksAreIgnored(): void
    {
        $html = '<a href="https://example.com/homepage">A</a> <a href="mailto:x@y.tld">B</a>';

        self::assertSame($html, $this->convert($html));
    }

    public function testUnknownSlugIsLeftAsIs(): void
    {
        $html = '<a href="/no-such-page-here">x</a>';

        self::assertSame($html, $this->convert($html));
    }

    /** `/` carries no slug to look up. */
    public function testRootLinkIsLeftAsIs(): void
    {
        $html = '<a href="/">Home</a>';

        self::assertSame($html, $this->convert($html));
    }

    public function testContentWithoutAnchorsIsReturnedAsIs(): void
    {
        $html = '<p>Just some text</p>';

        self::assertSame($html, $this->convert($html));
    }

    /** Without a page being rendered there is no host to resolve links against. */
    public function testNothingIsRewrittenOutsideOfAPageRender(): void
    {
        $html = '<a href="/homepage">Home</a>';

        self::assertSame($html, $this->convert($html, withCurrentPage: false));
    }

    /** A single-site request serves pages at their plain path, so the filter stands down. */
    public function testApplyIsANoOpWhenNoCustomPathIsInPlay(): void
    {
        $html = '<a href="/homepage">Home</a>';

        self::assertSame($html, $this->filter()->apply(
            $html,
            $this->page('homepage'),
            new ReflectionClass(Manager::class)->newInstanceWithoutConstructor(),
        ));
    }

    private function convert(string $html, bool $withCurrentPage = true): string
    {
        $filter = $this->filter();

        if ($withCurrentPage) {
            self::getContainer()->get(SiteRegistry::class)->setCurrentPage($this->page('homepage'));
        }

        return $filter->convertLinks($html);
    }

    private function filter(): HtmlLinkMultisite
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');

        return self::getContainer()->get(HtmlLinkMultisite::class);
    }

    private function page(string $slug): Page
    {
        $page = self::getContainer()->get(PageRepository::class)->getPageBySlug($slug, 'localhost.dev');
        self::assertNotNull($page, 'fixture page "'.$slug.'" is missing');

        return $page;
    }
}
