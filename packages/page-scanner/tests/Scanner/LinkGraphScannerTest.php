<?php

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Scanner\LinkGraphScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class LinkGraphScannerTest extends KernelTestCase
{
    private function createScanner(): LinkGraphScanner
    {
        self::bootKernel();

        return new LinkGraphScanner(
            self::getContainer()->get(SiteRegistry::class),
            self::getContainer()->get('translator'),
        );
    }

    private function createPage(string $slug = 'source'): Page
    {
        $page = new Page()->setSlug($slug);
        $page->host = 'localhost.dev';

        return $page;
    }

    /**
     * @return list<string>
     */
    private function targetsOf(string $html, string $slug = 'source'): array
    {
        $scanner = $this->createScanner();
        $scanner->scan($this->createPage($slug), $html);

        return $scanner->getEdges()['localhost.dev/'.$slug] ?? [];
    }

    public function testCollectsInternalHrefLinks(): void
    {
        self::assertSame(
            ['localhost.dev/one', 'localhost.dev/two'],
            $this->targetsOf('<a href="/one">1</a> <a class="x" href="/two">2</a>'),
        );
    }

    public function testIgnoresObfuscatedLinks(): void
    {
        // What link() actually renders: a <span>, no href, so no crawlable link.
        self::assertSame([], $this->targetsOf('<span data-rot="L29uZQ==">one</span>'));
    }

    public function testIgnoresUnpublishedLinks(): void
    {
        // What HtmlUnpublishedLink renders in place of an <a>.
        self::assertSame([], $this->targetsOf('<span data-status="unpublished" data-href="/one">one</span>'));
    }

    public function testIgnoresNonPageAttributes(): void
    {
        self::assertSame([], $this->targetsOf('<img src="/media/x.jpg" data-img="/media/y.jpg" data-bg="/media/z.jpg">'));
    }

    public function testIgnoresExternalAnchorMailtoAndRelativeLinks(): void
    {
        self::assertSame([], $this->targetsOf(
            '<a href="https://example.com/one">e</a>'
            .'<a href="//example.com/two">p</a>'
            .'<a href="#section">a</a>'
            .'<a href="mailto:x@example.com">m</a>'
            .'<a href="tel:+33">t</a>'
            .'<a href="relative">r</a>'
            .'<a href="">empty</a>',
        ));
    }

    public function testNormalizesTheHomepageSlug(): void
    {
        self::assertSame(['localhost.dev/homepage'], $this->targetsOf('<a href="/">home</a>'));
    }

    public function testStripsQueryAndAnchorAndTrailingSlash(): void
    {
        self::assertSame(['localhost.dev/one'], $this->targetsOf(
            '<a href="/one?utm=x">q</a><a href="/one#part">a</a><a href="/one/">s</a>',
        ));
    }

    public function testResolvesAbsoluteUrlsOnAKnownHost(): void
    {
        self::assertSame(['localhost.dev/one'], $this->targetsOf('<a href="https://localhost.dev/one">1</a>'));
    }

    public function testIgnoresSelfLinks(): void
    {
        self::assertSame([], $this->targetsOf('<a href="/source">me</a><a href="/source#part">me too</a>'));
    }

    public function testDeduplicatesRepeatedLinks(): void
    {
        self::assertSame(['localhost.dev/one'], $this->targetsOf('<a href="/one">1</a><a href="/one">again</a>'));
    }

    public function testARedirectionRendersNoHtmlSoItIsNotASource(): void
    {
        $scanner = $this->createScanner();
        $scanner->scan($this->createPage(), '');

        self::assertSame([], $scanner->getEdges());
    }

    public function testEdgesAccumulateAcrossPagesUntilReset(): void
    {
        $scanner = $this->createScanner();
        $scanner->scan($this->createPage('a'), '<a href="/one">1</a>');
        $scanner->scan($this->createPage('b'), '<a href="/two">2</a>');

        self::assertSame([
            'localhost.dev/a' => ['localhost.dev/one'],
            'localhost.dev/b' => ['localhost.dev/two'],
        ], $scanner->getEdges());

        $scanner->reset();
        self::assertSame([], $scanner->getEdges());
    }

    public function testReportsNoError(): void
    {
        self::assertSame([], $this->createScanner()->scan($this->createPage(), '<a href="/nowhere">x</a>'));
    }
}
