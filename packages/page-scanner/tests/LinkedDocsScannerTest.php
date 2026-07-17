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
