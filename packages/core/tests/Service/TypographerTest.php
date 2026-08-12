<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Typographer;

final class TypographerTest extends TestCase
{
    private const string NBSP = "\u{00A0}";

    private const string NNBSP = "\u{202F}";

    private Typographer $typographer;

    protected function setUp(): void
    {
        $this->typographer = new Typographer();
    }

    public function testFrenchRules(): void
    {
        $fixed = $this->typographer->fix("<p>Il a dit &quot;bonjour&quot; a l'ami : c'est vrai ! ok ; non ?</p>", 'fr');

        self::assertSame(
            '<p>Il a dit «'.self::NBSP.'bonjour'.self::NBSP.'» a l’ami'.self::NBSP.': c’est vrai'.self::NNBSP.'! ok'.self::NNBSP.'; non'.self::NNBSP.'?</p>',
            $fixed
        );
    }

    public function testEnglishRules(): void
    {
        self::assertSame(
            '<p>He said “hello” to everyone: right! Next…</p>',
            $this->typographer->fix('<p>He said &quot;hello&quot; to everyone : right ! Next...</p>', 'en')
        );
    }

    public function testGermanQuotes(): void
    {
        self::assertSame('<p>Er sagte „hallo“!</p>', $this->typographer->fix('<p>Er sagte &quot;hallo&quot; !</p>', 'de'));
    }

    public function testSwissGermanQuotes(): void
    {
        self::assertSame(
            '<p>Er sagte «'.self::NNBSP.'hallo'.self::NNBSP.'»!</p>',
            $this->typographer->fix('<p>Er sagte &quot;hallo&quot; !</p>', 'de-CH')
        );
    }

    public function testSwedishQuotes(): void
    {
        self::assertSame('<p>Han sa ”hej”</p>', $this->typographer->fix('<p>Han sa &quot;hej&quot;</p>', 'sv'));
    }

    public function testRegionalLocaleFallsBackToLanguage(): void
    {
        self::assertSame('<p>“quoted”</p>', $this->typographer->fix('<p>&quot;quoted&quot;</p>', 'en-GB'));
        self::assertStringContainsString('«'.self::NBSP, $this->typographer->fix('<p>&quot;cité&quot;</p>', 'fr_CA'));
    }

    public function testPlainQuoteCharacterAlsoHandled(): void
    {
        // Raw template text is not entity-encoded
        self::assertSame('Titre'.self::NBSP.': l’ete «'.self::NBSP.'guide'.self::NBSP.'»'.self::NNBSP.'!', $this->typographer->fix('Titre : l\'ete "guide" !', 'fr'));
    }

    public function testDimensionAndTrademark(): void
    {
        self::assertSame(
            '<p>Photo 30 × 40, ©'.self::NBSP.'2026 Pushword™®</p>',
            $this->typographer->fix('<p>Photo 30 x 40, (c) 2026 Pushword(tm)(r)</p>', 'en')
        );
    }

    public function testNoDashRule(): void
    {
        // The Dash and Hyphen JoliTypo rules are deliberately not ported
        self::assertSame(
            '<p>-1.014 m et 2 - 3 -- fin</p>',
            $this->typographer->fix('<p>-1.014 m et 2 - 3 -- fin</p>', 'fr')
        );
    }

    public function testProtectedTagsStayUntouched(): void
    {
        $html = '<pre>l\'a : "ok" !</pre><code>l\'b...</code><svg viewBox="0 0 20 20"><path d="m6 8 4-4"/></svg><script>if (a) { alert("l\'z"); }</script>';

        self::assertSame($html, $this->typographer->fix($html, 'fr'));
    }

    public function testNestedProtectedTags(): void
    {
        $html = "<pre><code>l'a...</code></pre><p>l'b</p>";

        self::assertSame('<pre><code>l\'a...</code></pre><p>l’b</p>', $this->typographer->fix($html, 'fr'));
    }

    public function testMarkupBytesArePreserved(): void
    {
        $html = '<div  class="a"><a href="/l\'apostrophe" title="l\'x">l\'y</a><!--break--><img src="/a.jpg"/></div>';
        $fixed = $this->typographer->fix($html, 'fr');

        self::assertSame('<div  class="a"><a href="/l\'apostrophe" title="l\'x">l’y</a><!--break--><img src="/a.jpg"/></div>', $fixed);
    }

    public function testIdempotent(): void
    {
        $inputs = [
            ['fr', "<p>Il a dit &quot;bonjour&quot; a l'ami : vrai ! 30 x 40 (c) 2026...</p>"],
            ['de-CH', '<p>Er sagte &quot;hallo&quot; ! 3x4</p>'],
            ['en', '<p>He said &quot;hi&quot; : yes !...</p>'],
        ];

        foreach ($inputs as [$locale, $input]) {
            $once = $this->typographer->fix($input, $locale);
            self::assertSame($once, $this->typographer->fix($once, $locale), 'Not idempotent for '.$locale);
        }
    }

    public function testEmptyAndWhitespaceOnly(): void
    {
        self::assertSame('', $this->typographer->fix('', 'fr'));
        self::assertSame("  \n", $this->typographer->fix("  \n", 'fr'));
    }

    public function testUnknownLocaleUsesDoubleQuotes(): void
    {
        self::assertSame('<p>“x”</p>', $this->typographer->fix('<p>&quot;x&quot;</p>', 'zz'));
    }

    public function testEntitiesInTextAreSafe(): void
    {
        // `;` closing an entity must never attract a narrow no-break space
        self::assertSame(
            '<p>A &amp; B &lt;3 &gt;2</p>',
            $this->typographer->fix('<p>A &amp; B &lt;3 &gt;2</p>', 'fr')
        );
    }

    public function testUnpairedQuoteUntouched(): void
    {
        self::assertSame('<p>a &quot;b</p>', $this->typographer->fix('<p>a &quot;b</p>', 'en'));
    }
}
