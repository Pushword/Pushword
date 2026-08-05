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
use Pushword\PageScanner\Scanner\ParallelUrlChecker;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('integration')]
final class LinkedDocsScannerTest extends KernelTestCase
{
    private function createScanner(?string $publicDir = null, ?CacheInterface $externalUrlCache = null): LinkedDocsScanner
    {
        return new LinkedDocsScanner(
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            self::getContainer()->get(SiteRegistry::class),
            [],
            $publicDir ?? __DIR__.'/../../dev-app/public',
            self::getContainer()->get('translator'),
            $externalUrlCache,
        );
    }

    /**
     * The two classes checking external URLs fill one cache pool — the parallel one
     * during a scan, this scanner on its synchronous fallback. They have to agree on
     * both the key and what is stored under it, or a warm cache reads as a miss at
     * best and a type error at worst.
     */
    public function testTheSynchronousPathReadsWhatTheParallelCheckerCached(): void
    {
        self::bootKernel();
        $url = 'https://cached.example.org/gone';

        $cache = self::getContainer()->get('cache.page_scanner');
        $cache->delete(ParallelUrlChecker::cacheKey($url));
        $cache->get(
            ParallelUrlChecker::cacheKey($url),
            static fn (): array => ['code' => 'link-status', 'message' => 'Unexpected status code (404)'],
        );

        try {
            $scanner = $this->createScanner(null, $cache);
            $scanner->preloadPageCache();

            $errors = $scanner->scan($this->getPage('other-page'), '<a href="'.$url.'">link</a>');

            self::assertCount(1, $errors, 'The cached failure must surface without a request.');
            self::assertSame('link-status', $errors[0]['code']);
        } finally {
            $cache->delete(ParallelUrlChecker::cacheKey($url));
        }
    }

    /**
     * And they have to agree on how long it is held. This path writes findings into
     * the shared pool too, so a 24h entry written here outlives the shorter life the
     * parallel checker gives the very same finding.
     */
    public function testTheSynchronousPathHoldsAFindingForTheShorterTtl(): void
    {
        self::bootKernel();
        $url = 'https://pushword-sync-ttl.invalid/x';

        $cache = self::getContainer()->get('cache.page_scanner');
        $cache->delete(ParallelUrlChecker::cacheKey($url));

        try {
            $scanner = $this->createScanner(null, $cache);
            $scanner->preloadPageCache();

            $errors = $scanner->scan($this->getPage('other-page'), '<a href="'.$url.'">link</a>');
            self::assertCount(1, $errors, 'An unresolvable host is a finding.');

            $expiry = $cache->getItem(ParallelUrlChecker::cacheKey($url))->getMetadata()[CacheItem::METADATA_EXPIRY];
            self::assertIsInt($expiry);
            $ttl = $expiry - time();

            self::assertGreaterThan(0, $ttl);
            self::assertLessThanOrEqual(3600, $ttl);
        } finally {
            $cache->delete(ParallelUrlChecker::cacheKey($url));
        }
    }

    public function testLinkedDocsScanner(): void
    {
        self::bootKernel();
        $errors = $this->messages($this->createScanner(), $this->getPage(), file_get_contents(__DIR__.'/data/page.html'));

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
        $errors = $this->messages($scanner, $this->getPage(), $html);

        self::assertSame([], $errors);
    }

    #[DataProvider('homepageUrlProvider')]
    public function testCrossHostInternalLinkToHomepage(string $url): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $errors = $this->messages($scanner, $this->getPage('other-page'), '<a href="'.$url.'">home</a>');

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

    /**
     * A media is not a page: resolving a cross-host URL against pages only reported
     * every absolute link to a file served by another host as a dead link.
     */
    #[DataProvider('crossHostMediaUrlProvider')]
    public function testCrossHostInternalLinkToMedia(string $url): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $errors = $this->messages($scanner, $this->getPage('other-page', 'pushword.piedweb.com'), '<a href="'.$url.'">doc</a>');

        self::assertSame([], $errors, $url.' exists and must not be reported');
    }

    /**
     * @return Iterator<string, array{string}>
     */
    public static function crossHostMediaUrlProvider(): Iterator
    {
        yield 'media' => ['https://localhost.dev/media/1.jpg'];
        yield 'media behind an image filter' => ['https://localhost.dev/media/xs/1.jpg'];
        yield 'media converted to another format' => ['https://localhost.dev/media/default/1.webp'];
        yield 'media with a query string' => ['https://localhost.dev/media/1.jpg?v=2'];
    }

    /**
     * The other half of what a host serves without owning a page for it: a plain file
     * under public/. Resolved against the public directory, which every host shares.
     */
    public function testCrossHostInternalLinkToStaticFile(): void
    {
        self::bootKernel();

        $publicDir = sys_get_temp_dir().'/pushword-page-scanner-public-'.getmypid();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($publicDir.'/downloads/brochure.pdf', 'a pdf');

        try {
            $scanner = $this->createScanner($publicDir);
            $scanner->preloadPageCache();

            $html = '<a href="https://localhost.dev/downloads/brochure.pdf">doc</a>';

            self::assertSame([], $this->messages($scanner, $this->getPage('other-page', 'pushword.piedweb.com'), $html));
        } finally {
            $filesystem->remove($publicDir);
        }
    }

    public function testCrossHostInternalLinkToMissingMedia(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $html = '<a href="https://localhost.dev/media/nonexistent.pdf">doc</a>';
        $errors = $this->messages($scanner, $this->getPage('other-page', 'pushword.piedweb.com'), $html);

        self::assertCount(1, $errors);
        self::assertStringContainsString('https://localhost.dev/media/nonexistent.pdf', $errors[0]);
    }

    /**
     * The root of a host is its homepage, whichever way the link spells it — it must
     * be resolved as that page, not merely as a path the public directory happens to
     * answer for, or the checks below "not found" never run on it.
     */
    public function testRootLinkResolvesToTheHomepage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        self::assertSame([], $this->messages($scanner, $this->getPage('other-page', 'localhost.dev'), '<a href="/">home</a>'));
    }

    /**
     * Pages are stored under their site's main host, so a link spelling one of the
     * site's alias hosts has to be resolved against the main one — otherwise every
     * `www.` link to a page of the installation reads as a dead link.
     */
    public function testCrossHostInternalLinkToAliasHost(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $target = new Page();
        $target->h1 = 'Alias target';
        $target->slug = 'alias-target';
        $target->host = 'admin-block-editor.test';
        $target->locale = 'en';
        $target->mainContent = '...';

        $em->persist($target);
        $em->flush();

        try {
            $scanner = $this->createScanner();
            $scanner->preloadPageCache();

            // www.admin-block-editor.test is an alias of admin-block-editor.test
            $html = '<a href="https://www.admin-block-editor.test/alias-target">link</a>';

            self::assertSame([], $this->messages($scanner, $this->getPage('other-page', 'localhost.dev'), $html));
        } finally {
            $em->remove($target);
            $em->flush();
        }
    }

    public function testCrossHostInternalLinkToMissingPage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // localhost.dev/nonexistent does not exist → "not found" error
        $html = '<a href="https://localhost.dev/nonexistent">link</a>';
        $errors = $this->messages($scanner, $this->getPage(), $html);

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
            $errors = $this->messages($scanner, $this->getPage('other-page'), $html);

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
            $errors = $this->messages($scanner, $this->getPage('other-page'), $html);

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
        $page->h1 = 'Future page';
        $page->slug = 'future-page';
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->mainContent = '...';
        $page->publishedAt = new DateTime('+1 year');

        return $page;
    }

    public function testCrossHostInternalLinkToRedirectionPage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // "pushword" page in fixtures has mainContent "Location: ..." → is a redirection
        $html = '<a href="https://localhost.dev/pushword">link</a>';
        $errors = $this->messages($scanner, $this->getPage('other-page'), $html);

        self::assertCount(1, $errors);
        self::assertStringContainsString('https://localhost.dev/pushword', $errors[0]);
    }

    #[DataProvider('redirectFromLinkProvider')]
    public function testInternalLinkToRedirectFromOldSlugReportedAsRedirection(string $linkingHost, string $href): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new Page();
        $destination->h1 = 'Scan Destination';
        $destination->slug = 'scan-destination';
        $destination->host = 'localhost.dev';
        $destination->locale = 'en';
        $destination->mainContent = 'content';
        $destination->redirectFrom = ['scan-old' => 301];

        $em->persist($destination);
        $em->flush();

        try {
            $scanner = $this->createScanner();
            $scanner->preloadPageCache();

            $translator = self::getContainer()->get(TranslatorInterface::class);
            $redirectionMsg = $translator->trans('page_scanIsRedirection');

            $errors = $this->messages($scanner, $this->getPage('scan-linking-page', $linkingHost), '<a href="'.$href.'">link</a>');

            self::assertContains('<code>'.$href.'</code> '.$redirectionMsg, $errors);
        } finally {
            $em->remove($destination);
            $em->flush();
        }
    }

    /**
     * @return Iterator<string, array{string, string}>
     */
    public static function redirectFromLinkProvider(): Iterator
    {
        yield 'root-relative, from the same host' => ['localhost.dev', '/scan-old'];
        yield 'absolute, from another host' => ['pushword.piedweb.com', 'https://localhost.dev/scan-old'];
    }

    public function testCrawlableLinkToNoindexPageIsReported(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $errors = $this->messages($scanner, $this->getPage('scan-linking-page', 'localhost.dev'), '<a href="/noindex-target">link</a>');

            self::assertSame(['<code>/noindex-target</code> '.$this->transNoindex()], $errors);
        });
    }

    public function testCrossHostCrawlableLinkToNoindexPageIsReported(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $html = '<a href="https://localhost.dev/noindex-target">link</a>';
            $errors = $this->messages($scanner, $this->getPage('other-page'), $html);

            self::assertSame(['<code>https://localhost.dev/noindex-target</code> '.$this->transNoindex()], $errors);
        });
    }

    /**
     * Cross-host links resolve through the same host-keyed cache as root-relative ones,
     * so a cache hit must restore what was found, not merely that something was.
     */
    public function testCrossHostNoindexLinkIsReportedOnEveryPageLinkingIt(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $html = '<a href="https://localhost.dev/noindex-target">link</a>';
            $expected = ['<code>https://localhost.dev/noindex-target</code> '.$this->transNoindex()];

            self::assertSame($expected, $this->messages($scanner, $this->getPage('scan-linking-page', 'pushword.piedweb.com'), $html));
            self::assertSame($expected, $this->messages($scanner, $this->getPage('another-linking-page', 'pushword.piedweb.com'), $html));
        });
    }

    public function testObfuscatedLinkToNoindexPageIsSilent(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            $html = '<span data-rot="'.LinkProvider::obfuscate('/noindex-target').'">link</span>';

            self::assertSame([], $this->messages($scanner, $this->getPage('scan-linking-page', 'localhost.dev'), $html));
        });
    }

    public function testIndexablePageIsNotReported(): void
    {
        $this->withNoindexPage(function (LinkedDocsScanner $scanner): void {
            self::assertSame([], $this->messages($scanner, $this->getPage('other-page'), '<a href="https://localhost.dev/homepage">link</a>'));
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

            self::assertSame($expected, $this->messages($scanner, $this->getPage('scan-linking-page', 'localhost.dev'), $html));
            self::assertSame($expected, $this->messages($scanner, $this->getPage('another-linking-page', 'localhost.dev'), $html));
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
                $this->messages($scanner, $this->getPage('scan-linking-page', 'localhost.dev'), $html),
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
            $this->messages($scanner, $this->getPage('scan-linking-page', 'localhost.dev'), '<a href="/noindex-target">link</a>');

            $redirection = $this->getPage('scan-redirection', 'localhost.dev');
            $redirection->mainContent = 'Location: /noindex-target';

            self::assertSame([], $this->messages($scanner, $redirection, ''));
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

        self::assertSame($expected, $this->messages($scanner, $this->getPage('scan-linking-page', 'localhost.dev'), $html));
        self::assertSame($expected, $this->messages($scanner, $this->getPage('another-linking-page', 'localhost.dev'), $html));
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
            $errors = $this->messages($scanner, $this->getPage('scan-linking-page', 'localhost.dev'), '<a href="/noindex-target">link</a>');

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
        yield 'none is the shorthand for noindex, nofollow' => ['none', true];
        yield 'noimageindex only bans images' => ['noimageindex', false];
        yield 'nosnippet does not contain none' => ['nosnippet, notranslate', false];
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
        $noindex->h1 = 'Noindex target';
        $noindex->slug = 'noindex-target';
        $noindex->host = 'localhost.dev';
        $noindex->locale = 'en';
        $noindex->mainContent = '...';
        $noindex->metaRobots = $metaRobots;

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
        $this->messages($scanner, $this->getPage(), $html);

        self::assertContains('https://unknown-host.com/page', $scanner->getCollectedExternalUrls());
    }

    public function testUnquotedHrefIsScanned(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();
        $scanner->enableCollectMode();

        $this->messages($scanner, $this->getPage(), '<a href=https://unknown-host.com/unquoted>link</a>');

        self::assertContains('https://unknown-host.com/unquoted', $scanner->getCollectedExternalUrls());
    }

    public function testObfuscatedLinkIsDecryptedAndScanned(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();
        $scanner->enableCollectMode();

        $html = '<span data-rot="'.LinkProvider::obfuscate('https://unknown-host.com/obfuscated').'">link</span>';
        $this->messages($scanner, $this->getPage(), $html);

        self::assertContains('https://unknown-host.com/obfuscated', $scanner->getCollectedExternalUrls());
    }

    public function testObfuscatedMailLinkRaisesNoError(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $html = '<span data-rot="'.LinkProvider::obfuscate('mailto:hello@example.tld').'">mail</span>';

        self::assertSame([], $this->messages($scanner, $this->getPage(), $html));
    }

    public function testPlainMailLinkRaisesObfuscateError(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $translator = self::getContainer()->get(TranslatorInterface::class);
        $errors = $this->messages($scanner, $this->getPage(), '<a href="mailto:hello@example.tld">mail</a>');

        self::assertContains('<code>mailto:hello@example.tld</code> '.$translator->trans('page_scanObfuscateMail'), $errors);
    }

    #[DataProvider('relativeLinkProvider')]
    public function testRelativeLinkIsReported(string $href): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $translator = self::getContainer()->get(TranslatorInterface::class);
        $errors = $this->messages($scanner, $this->getPage(), '<a href="'.$href.'">link</a>');

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

        self::assertSame([], $this->messages($scanner, $this->getPage(), $html));
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
            $this->messages($scanner, $this->getPage(), $html),
        );
    }

    public function testRelativeLinkCanBeIgnored(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // pageScanLinksToIgnore is set on the page fixture below
        self::assertSame([], $this->messages($scanner, $this->getPage(), '<a href="ignored-relative">link</a>'));
    }

    /**
     * `pageScanLinksToIgnore: ignored-relative` is the natural YAML for a page with
     * one link to skip, and it used to abort the whole scan on a LogicException —
     * where `pageScanErrorsToIgnore`, its twin, has always taken the same shape.
     */
    public function testTheCustomPropertyAcceptsASinglePattern(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $page = $this->getPage();
        $page->setCustomProperty('pageScanLinksToIgnore', 'ignored-relative');

        self::assertSame([], $this->messages($scanner, $page, '<a href="ignored-relative">link</a>'));
    }

    /**
     * The same list, declared in the content instead of the properties. A skipped link
     * is never fetched, so this is what a flaky external host calls for — silencing the
     * finding would still pay for the request on every scan.
     */
    public function testALinkCanBeIgnoredFromTheContent(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $url = 'https://localhost.dev/nonexistent';
        $html = '<a href="'.$url.'">link</a>';

        self::assertCount(1, $this->messages($scanner, $this->getPage('other-page'), $html), 'Nothing was ignored yet.');

        $page = $this->getPage('other-page');
        $page->mainContent = '<!-- page-scanner-ignore-link: https://localhost.dev/nonex* -->';

        self::assertSame([], $this->messages($scanner, $page, $html));
    }

    /**
     * An external URL is checked long after the page left scope, so what the page
     * asked not to be told about has to travel with the deferred check.
     */
    public function testAPageSilencesADeferredExternalFindingItDeclaresInline(): void
    {
        self::bootKernel();
        $url = 'https://unreachable.example.com/x';
        $failure = [$url => ['code' => 'link-unreachable', 'message' => 'unreachable']];

        $scanner = $this->createScanner();
        $scanner->preloadPageCache();
        $scanner->enableDeferredExternalMode();
        $scanner->scan($this->getPage('other-page'), '<a href="'.$url.'">link</a>');
        $scanner->setExternalUrlResults($failure);

        self::assertCount(1, $scanner->resolveDeferredExternalErrors(), 'Nothing was ignored yet.');

        $silenced = $this->getPage('other-page');
        $silenced->mainContent = '<!-- page-scanner-ignore: link-unreachable -->';

        $scanner->enableDeferredExternalMode();
        $scanner->scan($silenced, '<a href="'.$url.'">link</a>');
        $scanner->setExternalUrlResults($failure);

        self::assertSame([], $scanner->resolveDeferredExternalErrors());
    }

    /**
     * An unexpected status and an unreachable host are two different problems, and a
     * site silencing one keeps hearing about the other — so the finding carries which
     * of the two it is, all the way from the checker.
     */
    public function testAnExternalFailureKeepsTheCodeTheCheckerGaveIt(): void
    {
        self::bootKernel();
        $url = 'https://example.org/gone';

        $scanner = $this->createScanner();
        $scanner->preloadPageCache();
        $scanner->setExternalUrlResults([$url => ['code' => 'link-status', 'message' => 'Unexpected status code (404)']]);

        $errors = $scanner->scan($this->getPage('other-page'), '<a href="'.$url.'">link</a>');

        self::assertCount(1, $errors);
        self::assertSame('link-status', $errors[0]['code']);
        self::assertStringContainsString('404', $errors[0]['message']);
    }

    /**
     * The findings' messages — what nearly every assertion here is about.
     *
     * @return string[]
     */
    private function messages(LinkedDocsScanner $scanner, Page $page, string $pageHtml): array
    {
        return array_column($scanner->scan($page, $pageHtml), 'message');
    }

    private function getPage(string $slug = 'homepage', string $host = ''): Page
    {
        $page = new Page();
        $page->h1 = 'Welcome to Pushword !';
        $page->slug = $slug;
        $page->host = $host;
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->mainContent = '...';
        $page->setCustomProperty('pageScanLinksToIgnore', ['https://example2.tld/*', 'ignored-relative']);

        return $page;
    }
}
