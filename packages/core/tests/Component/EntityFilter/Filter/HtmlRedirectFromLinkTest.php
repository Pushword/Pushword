<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\HtmlRedirectFromLink;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use ReflectionClass;

final class HtmlRedirectFromLinkTest extends TestCase
{
    private function createManagerStub(): Manager
    {
        return new ReflectionClass(Manager::class)->newInstanceWithoutConstructor();
    }

    private function createPage(string $slug, string $host = 'localhost'): Page
    {
        $page = new Page(false);
        $page->setSlug($slug);
        $page->host = $host;
        $page->locale = 'en';

        return $page;
    }

    /**
     * @param array<string, string> $redirectFromMap host/old-slug => current slug
     */
    private function createFilter(array $redirectFromMap): HtmlRedirectFromLink
    {
        $repo = self::createStub(PageRepository::class);
        $repo->method('resolveRedirectFromSlug')->willReturnCallback(
            static fn (string $slug, string $host): ?string => $redirectFromMap[$host.'/'.$slug] ?? null,
        );

        return new HtmlRedirectFromLink($repo);
    }

    private function apply(HtmlRedirectFromLink $filter, string $content, Page $page): string
    {
        $result = $filter->apply($content, $page, $this->createManagerStub());
        self::assertIsString($result);

        return $result;
    }

    public function testOldSlugLinkIsRewrittenToCurrentSlug(): void
    {
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        $result = $this->apply($filter, '<a href="/old-name">Example</a>', $currentPage);

        self::assertSame('<a href="/new-name">Example</a>', $result);
    }

    public function testCurrentSlugLinkIsLeftAsIs(): void
    {
        // 'new-name' is a live page, not a redirectFrom entry → stub returns null.
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        $html = '<a href="/new-name">Example</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testFragmentAndQueryArePreserved(): void
    {
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        self::assertSame(
            '<a href="/new-name#section">x</a>',
            $this->apply($filter, '<a href="/old-name#section">x</a>', $currentPage),
        );
        self::assertSame(
            '<a href="/new-name?id=1#top">x</a>',
            $this->apply($filter, '<a href="/old-name?id=1#top">x</a>', $currentPage),
        );
    }

    public function testOtherAttributesArePreserved(): void
    {
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        $result = $this->apply($filter, '<a class="btn" href="/old-name" rel="obfuscate">x</a>', $currentPage);

        self::assertSame('<a class="btn" href="/new-name" rel="obfuscate">x</a>', $result);
    }

    public function testTrailingSlashOnOldPathIsRewritten(): void
    {
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        self::assertSame(
            '<a href="/new-name">x</a>',
            $this->apply($filter, '<a href="/old-name/">x</a>', $currentPage),
        );
    }

    public function testRootHomepageLinkIsLeftAsIs(): void
    {
        // href="/" resolves to the 'homepage' slug, which is a live page (never a
        // redirectFrom entry) → stub returns null, link untouched.
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('about');

        $html = '<a href="/">Home</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testAbsoluteSameHostUrlIsNotRewritten(): void
    {
        // Documented limitation: only root-relative links are rewritten.
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        $html = '<a href="https://localhost/old-name">x</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testExternalAndSpecialLinksAreIgnored(): void
    {
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        $html = '<a href="https://example.com/old-name">A</a> <a href="#old-name">B</a> <a href="mailto:x@y.tld">C</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testUnknownSlugIsLeftAsIs(): void
    {
        $filter = $this->createFilter([]);
        $currentPage = $this->createPage('home');

        $html = '<a href="/no-such-page">link</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testMixedLinksOnlyRewriteOldSlugs(): void
    {
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        $html = '<a href="/old-name">One</a> and <a href="/live">Two</a>';
        $result = $this->apply($filter, $html, $currentPage);

        self::assertSame('<a href="/new-name">One</a> and <a href="/live">Two</a>', $result);
    }

    public function testContentWithoutAnchorsIsReturnedAsIs(): void
    {
        $filter = $this->createFilter(['localhost/old-name' => 'new-name']);
        $currentPage = $this->createPage('home');

        $html = '<p>Just some text without links</p>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }
}
