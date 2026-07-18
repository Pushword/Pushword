<?php

namespace Pushword\PageScanner;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Scanner\LinkedDocsScanner;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('integration')]
final class LinkedDocsScannerTest extends KernelTestCase
{
    private function createScanner(): LinkedDocsScanner
    {
        return new LinkedDocsScanner(
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            self::getContainer()->get(SiteRegistry::class),
            [],
            __DIR__.'/../../skeleton/public',
            self::getContainer()->get('translator'),
        );
    }

    public function testLinkedDocsScanner(): void
    {
        self::bootKernel();
        $errors = $this->createScanner()->scan($this->getPage(), file_get_contents(__DIR__.'/data/page.html'));

        self::assertContains('<code>#install</code> target not found', $errors);
        self::assertNotContains('<code>#fun</code> target not found', $errors);
    }

    public function testCrossHostInternalLinkToExistingPage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // localhost.dev/homepage exists in fixtures → no error
        $html = '<a href="https://localhost.dev/homepage">link</a>';
        $errors = $scanner->scan($this->getPage(), $html);

        self::assertSame([], $errors);
    }

    #[DataProvider('homepageUrlProvider')]
    public function testCrossHostInternalLinkToHomepage(string $url): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $errors = $scanner->scan($this->getPage('other-page'), '<a href="'.$url.'">home</a>');

        self::assertSame([], $errors, $url.' should resolve internally without error');
    }

    /**
     * @return Iterator<string, array{string}>
     */
    public static function homepageUrlProvider(): Iterator
    {
        yield 'with trailing slash' => ['https://localhost.dev/'];
        yield 'without trailing slash' => ['https://localhost.dev'];
    }

    public function testCrossHostInternalLinkToMissingPage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // localhost.dev/nonexistent does not exist → "not found" error
        $html = '<a href="https://localhost.dev/nonexistent">link</a>';
        $errors = $scanner->scan($this->getPage(), $html);

        self::assertCount(1, $errors);
        self::assertStringContainsString('https://localhost.dev/nonexistent', $errors[0]);
    }

    public function testCrossHostInternalLinkToUnpublishedPageIgnoredByDefault(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $unpublished = $this->createUnpublishedFuturePage();
        $em->persist($unpublished);
        $em->flush();

        try {
            $scanner = $this->createScanner();
            $scanner->preloadPageCache();

            $html = '<a href="https://localhost.dev/future-page">link</a>';
            $errors = $scanner->scan($this->getPage('other-page'), $html);

            self::assertSame([], $errors, 'unpublished targets must be silent by default');
        } finally {
            $em->remove($unpublished);
            $em->flush();
        }
    }

    public function testCrossHostInternalLinkToUnpublishedPageReportedWhenEnabled(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $unpublished = $this->createUnpublishedFuturePage();
        $em->persist($unpublished);
        $em->flush();

        try {
            $scanner = $this->createScanner();
            $scanner->preloadPageCache();
            $scanner->enableCheckUnpublished();

            $html = '<a href="https://localhost.dev/future-page">link</a>';
            $errors = $scanner->scan($this->getPage('other-page'), $html);

            self::assertCount(1, $errors);
            self::assertStringContainsString('https://localhost.dev/future-page', $errors[0]);
        } finally {
            $em->remove($unpublished);
            $em->flush();
        }
    }

    private function createUnpublishedFuturePage(): Page
    {
        $page = new Page();
        $page->setH1('Future page');
        $page->setSlug('future-page');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('...');
        $page->setPublishedAt(new DateTime('+1 year'));

        return $page;
    }

    public function testCrossHostInternalLinkToRedirectionPage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // "pushword" page in fixtures has mainContent "Location: ..." → is a redirection
        $html = '<a href="https://localhost.dev/pushword">link</a>';
        $errors = $scanner->scan($this->getPage('other-page'), $html);

        self::assertCount(1, $errors);
        self::assertStringContainsString('https://localhost.dev/pushword', $errors[0]);
    }

    public function testInternalLinkToRedirectFromOldSlugReportedAsRedirection(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new Page();
        $destination->setH1('Scan Destination');
        $destination->setSlug('scan-destination');
        $destination->host = 'localhost.dev';
        $destination->locale = 'en';
        $destination->setMainContent('content');
        $destination->setRedirectFrom(['scan-old' => 301]);

        $em->persist($destination);
        $em->flush();

        try {
            $scanner = $this->createScanner();
            $scanner->preloadPageCache();

            $translator = self::getContainer()->get(TranslatorInterface::class);
            $redirectionMsg = $translator->trans('page_scanIsRedirection');

            $errors = $scanner->scan($this->getPage('scan-linking-page', 'localhost.dev'), '<a href="/scan-old">link</a>');

            self::assertContains('<code>/scan-old</code> '.$redirectionMsg, $errors);
        } finally {
            $em->remove($destination);
            $em->flush();
        }
    }

    public function testCrawlableLinkToNoindexPageIsReported(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $errors = $scanner->scan($this->getPage('scan-linking-page', 'localhost.dev'), '<a href="/noindex-target">link</a>');

            self::assertSame(['<code>/noindex-target</code> '.$this->transNoindex()], $errors);
        });
    }

    public function testCrossHostCrawlableLinkToNoindexPageIsReported(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $html = '<a href="https://localhost.dev/noindex-target">link</a>';
            $errors = $scanner->scan($this->getPage('other-page'), $html);

            self::assertSame(['<code>https://localhost.dev/noindex-target</code> '.$this->transNoindex()], $errors);
        });
    }

    public function testObfuscatedLinkToNoindexPageIsSilent(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $html = '<span data-rot="'.LinkProvider::obfuscate('/noindex-target').'">link</span>';

            self::assertSame([], $scanner->scan($this->getPage('scan-linking-page', 'localhost.dev'), $html));
        });
    }

    public function testIndexablePageIsNotReported(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            self::assertSame([], $scanner->scan($this->getPage('other-page'), '<a href="https://localhost.dev/homepage">link</a>'));
        });
    }

    /**
     * A nav or footer link to a `noindex` page is the common case: the report is
     * worthless if the second page linking the same target goes silent.
     */
    public function testNoindexLinkIsReportedOnEveryPageLinkingIt(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $html = '<a href="/noindex-target">link</a>';
            $expected = ['<code>/noindex-target</code> '.$this->transNoindex()];

            self::assertSame($expected, $scanner->scan($this->getPage('scan-linking-page', 'localhost.dev'), $html));
            self::assertSame($expected, $scanner->scan($this->getPage('another-linking-page', 'localhost.dev'), $html));
        });
    }

    /**
     * The same target written both ways on one page stays a crawlable link: the
     * plain href is what a robot follows, whatever else surrounds it.
     */
    public function testTargetLinkedBothPlainAndObfuscatedIsReported(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $html = '<span data-rot="'.LinkProvider::obfuscate('/noindex-target').'">link</span>'
                .'<a href="/noindex-target">link</a>';

            self::assertSame(
                ['<code>/noindex-target</code> '.$this->transNoindex()],
                $scanner->scan($this->getPage('scan-linking-page', 'localhost.dev'), $html),
            );
        });
    }

    /**
     * A redirection page is checked before any link is collected, so the link set
     * it is matched against must be its own, not the page scanned before it.
     */
    public function testRedirectionTargetIsNotJudgedOnThePreviousPageLinks(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $scanner->scan($this->getPage('scan-linking-page', 'localhost.dev'), '<a href="/noindex-target">link</a>');

            $redirection = $this->getPage('scan-redirection', 'localhost.dev');
            $redirection->setMainContent('Location: /noindex-target');

            self::assertSame([], $scanner->scan($redirection, ''));
        });
    }

    /**
     * Same cache as the noindex check: the redirect warning used to fire only on
     * the first page linking a given slug.
     */
    public function testRedirectionIsReportedOnEveryPageLinkingIt(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // "pushword" page in fixtures has mainContent "Location: ..." → is a redirection
        $expected = ['<code>/pushword</code> '.self::getContainer()->get(TranslatorInterface::class)->trans('page_scanIsRedirection')];
        $html = '<a href="/pushword">link</a>';

        self::assertSame($expected, $scanner->scan($this->getPage('scan-linking-page', 'localhost.dev'), $html));
        self::assertSame($expected, $scanner->scan($this->getPage('another-linking-page', 'localhost.dev'), $html));
    }

    /**
     * Delegated to {@see Page::hasNoindex()}, so this only guards the delegation:
     * whatever the graph and the sitemap treat as noindex, the scanner warns about,
     * and warning about a link they still count as indexable would be the worse bug.
     */
    #[DataProvider('metaRobotsProvider')]
    public function testMetaRobotsVariants(string $metaRobots, bool $expectReport): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner) use ($expectReport): void {
            $errors = $scanner->scan($this->getPage('scan-linking-page', 'localhost.dev'), '<a href="/noindex-target">link</a>');

            self::assertSame(
                $expectReport ? ['<code>/noindex-target</code> '.$this->transNoindex()] : [],
                $errors,
            );
        }, $metaRobots);
    }

    /**
     * @return Iterator<string, array{string, bool}>
     */
    public static function metaRobotsProvider(): Iterator
    {
        yield 'bare noindex' => ['noindex', true];
        yield 'noindex among other directives' => ['noindex, noarchive', true];
        yield 'no space after the comma' => ['noindex,nofollow', true];
        yield 'uppercase, as a flat file may spell it' => ['NOINDEX', true];
        yield 'noimageindex only bans images' => ['noimageindex', false];
        yield 'explicitly indexable' => ['index, follow', false];
        yield 'unrelated directive' => ['noarchive', false];
        yield 'empty, the default' => ['', false];
    }

    private function transNoindex(): string
    {
        return self::getContainer()->get(TranslatorInterface::class)->trans('page_scanNoindexLink');
    }

    /**
     * @param callable(LinkedDocsScanner): void $assert
     */
    private function withNoindexPage(callable $assert, string $metaRobots = 'noindex, follow'): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $noindex = new Page();
        $noindex->setH1('Noindex target');
        $noindex->setSlug('noindex-target');
        $noindex->host = 'localhost.dev';
        $noindex->locale = 'en';
        $noindex->setMainContent('...');
        $noindex->setMetaRobots($metaRobots);

        $em->persist($noindex);
        $em->flush();

        try {
            $scanner = $this->createScanner();
            $scanner->preloadPageCache();

            $assert($scanner);
        } finally {
            $em->remove($noindex);
            $em->flush();
        }
    }

    public function testExternalLinkStillTreatedAsExternal(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();
        $scanner->enableCollectMode();

        // unknown-host.com is not a known Pushword host → collected as external
        $html = '<a href="https://unknown-host.com/page">link</a>';
        $scanner->scan($this->getPage(), $html);

        self::assertContains('https://unknown-host.com/page', $scanner->getCollectedExternalUrls());
    }

    public function testUnquotedHrefIsScanned(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();
        $scanner->enableCollectMode();

        $scanner->scan($this->getPage(), '<a href=https://unknown-host.com/unquoted>link</a>');

        self::assertContains('https://unknown-host.com/unquoted', $scanner->getCollectedExternalUrls());
    }

    public function testObfuscatedLinkIsDecryptedAndScanned(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();
        $scanner->enableCollectMode();

        $html = '<span data-rot="'.LinkProvider::obfuscate('https://unknown-host.com/obfuscated').'">link</span>';
        $scanner->scan($this->getPage(), $html);

        self::assertContains('https://unknown-host.com/obfuscated', $scanner->getCollectedExternalUrls());
    }

    public function testObfuscatedMailLinkRaisesNoError(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $html = '<span data-rot="'.LinkProvider::obfuscate('mailto:hello@example.tld').'">mail</span>';

        self::assertSame([], $scanner->scan($this->getPage(), $html));
    }

    public function testPlainMailLinkRaisesObfuscateError(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $translator = self::getContainer()->get(TranslatorInterface::class);
        $errors = $scanner->scan($this->getPage(), '<a href="mailto:hello@example.tld">mail</a>');

        self::assertContains('<code>mailto:hello@example.tld</code> '.$translator->trans('page_scanObfuscateMail'), $errors);
    }

    #[DataProvider('relativeLinkProvider')]
    public function testRelativeLinkIsReported(string $href): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $translator = self::getContainer()->get(TranslatorInterface::class);
        $errors = $scanner->scan($this->getPage(), '<a href="'.$href.'">link</a>');

        self::assertContains('<code>'.$href.'</code> '.$translator->trans('page_scanRelativeLink'), $errors);
    }

    /**
     * @return Iterator<string, array{string}>
     */
    public static function relativeLinkProvider(): Iterator
    {
        yield 'slug missing its leading slash' => ['pushword'];
        yield 'existing slug missing its leading slash' => ['homepage'];
        yield 'nested relative path' => ['../pushword'];
        yield 'relative slug with anchor' => ['pushword#install'];
    }

    #[DataProvider('codeSampleProvider')]
    public function testLinksInsideCodeSamplesAreNotScanned(string $html): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        self::assertSame([], $scanner->scan($this->getPage(), $html));
    }

    /**
     * @return Iterator<string, array{string}>
     */
    public static function codeSampleProvider(): Iterator
    {
        // Markdown escapes < and > inside code, but not the quotes
        yield 'relative href in inline code' => ['<code>&lt;a href="..."&gt;text&lt;/a&gt;</code>'];
        yield 'absolute href in inline code' => ['<code>&lt;a href="/not-a-page"&gt;text&lt;/a&gt;</code>'];
        yield 'href in a fenced block' => ['<pre><code>&lt;a href="/not-a-page"&gt;x&lt;/a&gt;</code></pre>'];
        yield 'src in inline code' => ['<code>&lt;img src="/nope.png"&gt;</code>'];

        // The shape Markdown really emits: a language class, and a trailing
        // newline before </code>.
        yield 'fenced block with a language class spanning lines' => [
            '<pre><code class="language-html">&lt;a href="/not-a-page"&gt;x&lt;/a&gt;'."\n".'</code></pre>',
        ];

        // An attribute on the outer tag must not defeat the strip.
        yield 'attribute on the wrapping tag' => [
            '<pre class="highlight">&lt;a href="/not-a-page"&gt;x&lt;/a&gt;</pre>',
        ];
        yield 'attribute on an unwrapped code tag' => [
            '<code class="language-html">&lt;a href="/not-a-page"&gt;x&lt;/a&gt;</code>',
        ];
    }

    public function testCodeSamplesDoNotSwallowTheLinksBetweenThem(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $translator = self::getContainer()->get(TranslatorInterface::class);
        $html = '<code>&lt;a href="/first"&gt;x&lt;/a&gt;</code>'
            .'<a href="/not-a-page">real</a>'
            .'<pre><code class="language-html">&lt;a href="/second"&gt;x&lt;/a&gt;</code></pre>';

        self::assertSame(
            ['<code>/not-a-page</code> '.$translator->trans('page_scanNotFound')],
            $scanner->scan($this->getPage(), $html),
        );
    }

    public function testRelativeLinkCanBeIgnored(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // pageScanLinksToIgnore is set on the page fixture below
        self::assertSame([], $scanner->scan($this->getPage(), '<a href="ignored-relative">link</a>'));
    }

    private function getPage(string $slug = 'homepage', string $host = ''): Page
    {
        $page = new Page();
        $page->setH1('Welcome to Pushword !');
        $page->setSlug($slug);
        $page->host = $host;
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->setMainContent('...');
        $page->setCustomProperty('pageScanLinksToIgnore', ['https://example2.tld/*', 'ignored-relative']);

        return $page;
    }
}
