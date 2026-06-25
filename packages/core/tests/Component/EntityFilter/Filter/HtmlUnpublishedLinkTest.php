<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use DateTime;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\HtmlUnpublishedLink;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use ReflectionClass;

final class HtmlUnpublishedLinkTest extends TestCase
{
    private function createManagerStub(): Manager
    {
        return new ReflectionClass(Manager::class)->newInstanceWithoutConstructor();
    }

    private function createPage(string $slug, ?DateTime $publishedAt = null, string $host = 'localhost'): Page
    {
        $page = new Page(false);
        $page->setSlug($slug);
        $page->host = $host;
        $page->locale = 'en';
        $page->setPublishedAt($publishedAt);

        return $page;
    }

    /**
     * @param array<string, Page|null> $slugMap host/slug => Page|null
     */
    private function createFilter(array $slugMap): HtmlUnpublishedLink
    {
        $repo = self::createStub(PageRepository::class);
        $repo->method('getPageBySlug')->willReturnCallback(
            static fn (string $slug, string $host): ?Page => $slugMap[$host.'/'.$slug] ?? null,
        );

        return new HtmlUnpublishedLink($repo);
    }

    private function apply(HtmlUnpublishedLink $filter, string $content, Page $page): string
    {
        $result = $filter->apply($content, $page, $this->createManagerStub());
        self::assertIsString($result);

        return $result;
    }

    public function testPublishedLinkIsUnchanged(): void
    {
        $published = $this->createPage('hello', new DateTime('-1 day'));
        $filter = $this->createFilter(['localhost/hello' => $published]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $html = '<a href="/hello">Hello</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testUnpublishedLinkIsReplacedWithSpan(): void
    {
        $draft = $this->createPage('draft');
        $filter = $this->createFilter(['localhost/draft' => $draft]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $result = $this->apply($filter, 'Read <a href="/draft">my draft</a> now', $currentPage);

        self::assertSame(
            'Read <span title="Page en cours de publication" data-status="unpublished" data-href="/draft">my draft</span> now',
            $result,
        );
    }

    public function testScheduledLinkIsReplacedWithSpan(): void
    {
        $scheduled = $this->createPage('coming', new DateTime('+1 day'));
        $filter = $this->createFilter(['localhost/coming' => $scheduled]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $result = $this->apply($filter, '<a href="/coming">Soon</a>', $currentPage);

        self::assertStringContainsString('data-status="unpublished"', $result);
        self::assertStringContainsString('data-href="/coming"', $result);
        self::assertStringContainsString('>Soon</span>', $result);
    }

    public function testAnchorMailtoTelAreIgnored(): void
    {
        $filter = $this->createFilter([]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $html = '<a href="#section">A</a> <a href="mailto:x@y.tld">B</a> <a href="tel:+1234">C</a> <a href="javascript:void(0)">D</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testExternalLinkIsIgnored(): void
    {
        $filter = $this->createFilter([]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $html = '<a href="https://example.com/x">External</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testUnknownSlugIsLeftAsIs(): void
    {
        $filter = $this->createFilter([]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $html = '<a href="/no-such-page">link</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    public function testObfuscatedRelLinkToUnpublishedIsMasked(): void
    {
        // The filter runs before HtmlObfuscateLink, so rel="obfuscate" hasn't
        // been processed yet; our filter sees it as a normal <a href> and
        // masks it if the target is a draft. Once masked, the obfuscator can
        // no longer match it (no <a href>+rel="obfuscate" pair survives).
        $draft = $this->createPage('draft');
        $filter = $this->createFilter(['localhost/draft' => $draft]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $result = $this->apply($filter, '<a href="/draft" rel="obfuscate">Click</a>', $currentPage);

        self::assertSame(
            '<span title="Page en cours de publication" data-status="unpublished" data-href="/draft">Click</span>',
            $result,
        );
    }

    public function testMixedPublishedAndUnpublishedLinks(): void
    {
        $pub = $this->createPage('pub', new DateTime('-1 day'));
        $draft = $this->createPage('draft');
        $filter = $this->createFilter([
            'localhost/pub' => $pub,
            'localhost/draft' => $draft,
        ]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $html = '<a href="/pub">One</a> and <a href="/draft">Two</a> and <a href="/pub">Three</a>';
        $result = $this->apply($filter, $html, $currentPage);

        self::assertStringContainsString('<a href="/pub">One</a>', $result);
        self::assertStringContainsString('<a href="/pub">Three</a>', $result);
        self::assertStringContainsString('<span title="Page en cours de publication" data-status="unpublished" data-href="/draft">Two</span>', $result);
    }

    public function testNestedHtmlInsideLinkIsPreserved(): void
    {
        $draft = $this->createPage('draft');
        $filter = $this->createFilter(['localhost/draft' => $draft]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $result = $this->apply($filter, '<a href="/draft"><strong>bold</strong> and <em>italic</em></a>', $currentPage);

        self::assertStringContainsString('<strong>bold</strong> and <em>italic</em></span>', $result);
    }

    public function testAbsoluteUrlSameHostIsResolved(): void
    {
        $draft = $this->createPage('draft');
        $filter = $this->createFilter(['localhost/draft' => $draft]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $result = $this->apply($filter, '<a href="https://localhost/draft?foo=bar#anchor">x</a>', $currentPage);

        self::assertStringContainsString('data-status="unpublished"', $result);
        self::assertStringContainsString('data-href="https://localhost/draft?foo=bar#anchor"', $result);
    }

    public function testHrefWithFragmentAndQueryStringIsHandled(): void
    {
        $draft = $this->createPage('draft');
        $filter = $this->createFilter(['localhost/draft' => $draft]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $result = $this->apply($filter, '<a href="/draft?id=1#top">x</a>', $currentPage);

        self::assertStringContainsString('data-status="unpublished"', $result);
        self::assertStringContainsString('data-href="/draft?id=1#top"', $result);
    }

    public function testHomepageHrefResolvesToHomepageSlug(): void
    {
        $draftHome = $this->createPage('homepage');
        $filter = $this->createFilter(['localhost/homepage' => $draftHome]);
        $currentPage = $this->createPage('about', new DateTime('-1 day'));

        $result = $this->apply($filter, '<a href="/">Home</a>', $currentPage);

        self::assertStringContainsString('data-status="unpublished"', $result);
    }

    public function testContentWithoutAnchorsIsReturnedAsIs(): void
    {
        $filter = $this->createFilter([]);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $html = '<p>Just some text without links</p>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    /**
     * Every internal link target is batch-warmed in a single query per host
     * before the per-link resolution, instead of one getPageBySlug() per link.
     * This filter runs on fully rendered HTML (incl. Twig/listing links the
     * LinkCollector never saw), so without this it is a per-link N+1 — ~140
     * SELECTs on one /equipe render. External / mailto / anchor links are skipped.
     */
    public function testLinkTargetsAreBatchWarmedOncePerHost(): void
    {
        /** @var array<string, list<string>> $warmed host => slugs */
        $warmed = [];

        $repo = $this->createMock(PageRepository::class);
        $repo->method('warmupSlugCacheFor')->willReturnCallback(
            static function (array $slugs, string $host) use (&$warmed): void {
                // Each host must be warmed exactly once (the whole point of batching).
                self::assertArrayNotHasKey($host, $warmed, 'host '.$host.' warmed more than once');
                $warmed[$host] = $slugs;
            },
        );
        $repo->method('getPageBySlug')->willReturn(null);

        $filter = new HtmlUnpublishedLink($repo);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        // 4 same-host internal links (one absolute same-host) + one cross-host
        // multisite link + skipped anchor/mailto/external-domain links.
        $html = '<a href="/a">A</a> <a href="/b">B</a> <a href="#x">anchor</a>'
            .' <a href="mailto:x@y.tld">mail</a> <a href="/c">C</a>'
            .' <a href="https://localhost/d?q=1#t">D</a>'
            .' <a href="https://brand2.example/e">multisite</a>';

        $this->apply($filter, $html, $currentPage);

        // All four current-host targets collapse into a single warm-up call…
        self::assertSame(['a', 'b', 'c', 'd'], $warmed['localhost'] ?? null);
        // …and a cross-host (multisite) target is batched under its own host.
        self::assertSame(['e'], $warmed['brand2.example'] ?? null);
    }

    /**
     * When the body has <a> tags but none resolve to a (host, slug) target —
     * anchors, mailto/tel/javascript schemes, and schemeless-relative hrefs —
     * warmupLinkTargets collects no slugs and must never call
     * warmupSlugCacheFor() — no empty-batch query.
     *
     * Note: an absolute http(s) href is NOT considered "external" here; the
     * filter has no known-host allowlist, so it treats any URL with a host as a
     * potential page on that host (see the multisite case in
     * testLinkTargetsAreBatchWarmedOncePerHost). Only http(s) URLs with no host
     * are dropped.
     */
    public function testNoInternalLinksWarmsNothing(): void
    {
        $repo = $this->createMock(PageRepository::class);
        $repo->expects(self::never())->method('warmupSlugCacheFor');
        $repo->method('getPageBySlug')->willReturn(null);

        $filter = new HtmlUnpublishedLink($repo);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $html = '<a href="#x">anchor</a> <a href="mailto:x@y.tld">mail</a>'
            .' <a href="tel:+1234">tel</a> <a href="javascript:void(0)">js</a>'
            .' <a href="relative/path">rel</a>';

        self::assertSame($html, $this->apply($filter, $html, $currentPage));
    }

    /**
     * Repeated internal links to the same slug are passed to the warm-up as-is;
     * the filter does not de-duplicate (that is the repository's job). This pins
     * the actual collection behaviour so a future change to it is deliberate.
     */
    public function testDuplicateSlugsArePassedThroughWithoutDeduplication(): void
    {
        /** @var array<string, list<string>> $warmed host => slugs */
        $warmed = [];

        $repo = $this->createMock(PageRepository::class);
        $repo->method('warmupSlugCacheFor')->willReturnCallback(
            static function (array $slugs, string $host) use (&$warmed): void {
                $warmed[$host] = $slugs;
            },
        );
        $repo->method('getPageBySlug')->willReturn(null);

        $filter = new HtmlUnpublishedLink($repo);
        $currentPage = $this->createPage('home', new DateTime('-1 day'));

        $html = '<a href="/pub">One</a> <a href="/pub">Two</a> <a href="/other">Three</a>';

        $this->apply($filter, $html, $currentPage);

        self::assertSame(['pub', 'pub', 'other'], $warmed['localhost'] ?? null);
    }
}
