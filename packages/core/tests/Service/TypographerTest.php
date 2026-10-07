<?php

declare(strict_types=1);

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

    /** Quote style and spacing must match JoliTypo's LocaleConfig for each supported locale. */
    public function testSupportedLocales(): void
    {
        $input = '<p>Il dit &quot;oui&quot; la : fin !</p>';

        $guillemets = '<p>Il dit «oui» la: fin!</p>';
        $double = '<p>Il dit “oui” la: fin!</p>';

        $expectations = [
            'fr' => '<p>Il dit «'.self::NBSP.'oui'.self::NBSP.'» la'.self::NBSP.': fin'.self::NNBSP.'!</p>',
            'fr-CH' => '<p>Il dit «'.self::NBSP.'oui'.self::NBSP.'» la'.self::NBSP.': fin'.self::NNBSP.'!</p>',
            // Canadian French keeps the French quotes but the English spacing
            'fr-CA' => '<p>Il dit «'.self::NBSP.'oui'.self::NBSP.'» la: fin!</p>',
            'en' => $double,
            'en-GB' => $double,
            'en-IE' => $double,
            'en-AU' => $double,
            'en-CA' => $double,
            'de' => '<p>Il dit „oui“ la: fin!</p>',
            'de-CH' => '<p>Il dit «'.self::NNBSP.'oui'.self::NNBSP.'» la: fin!</p>',
            'it' => $guillemets,
            'it-CH' => $guillemets,
            'es' => $guillemets,
            'da-DK' => $guillemets,
            'nb-NO' => $guillemets,
            'fi' => '<p>Il dit ”oui” la: fin!</p>',
            'sv' => '<p>Il dit ”oui” la: fin!</p>',
        ];

        foreach ($expectations as $locale => $expected) {
            self::assertSame($expected, $this->typographer->fix($input, $locale), 'Locale '.$locale);
        }
    }

    public function testPlainQuoteCharacterAlsoHandled(): void
    {
        // Raw template text is not entity-encoded
        self::assertSame('Titre'.self::NBSP.': l’ete «'.self::NBSP.'guide'.self::NBSP.'»'.self::NNBSP.'!', $this->typographer->fix('Titre : l\'ete "guide" !', 'fr'));
    }

    /** The flat export straightens `« desert »` into `" desert "`: its spaces must not double the locale's own. */
    public function testSpacesInsideAStraightenedPairAreDropped(): void
    {
        self::assertSame(
            '<p>Le «'.self::NBSP.'desert'.self::NBSP.'» et le «'.self::NBSP.'canyon'.self::NBSP.'»</p>',
            $this->typographer->fix('<p>Le &quot; desert &quot; et le &quot;'.self::NBSP.'canyon'.self::NBSP.'&quot;</p>', 'fr')
        );
        self::assertSame('Le «'.self::NBSP.'desert'.self::NBSP.'»', $this->typographer->fix('Le " desert "', 'fr'));
        self::assertSame('<p>The “desert” and the “canyon”</p>', $this->typographer->fix('<p>The &quot; desert &quot; and the &quot; canyon &quot;</p>', 'en'));
    }

    public function testSpacesInsideAPairAreDroppedOnEitherSideAndOfAnyWidth(): void
    {
        self::assertSame('<p>“desert” and “canyon”</p>', $this->typographer->fix('<p>&quot; desert&quot; and &quot;canyon &quot;</p>', 'en'));
        self::assertSame('<p>“desert”</p>', $this->typographer->fix("<p>&quot;\t desert".self::NNBSP.'&quot;</p>', 'en'));
    }

    public function testSpacesInsideAPairAreDroppedAroundMarkupNestedQuotesAndMeasurements(): void
    {
        self::assertSame('<p>“<em>desert</em>”</p>', $this->typographer->fix('<p>&quot; <em>desert</em> &quot;</p>', 'en'));
        self::assertSame('<p><em>“desert”</em> (“canyon”)</p>', $this->typographer->fix('<p><em>&quot; desert &quot;</em> (&quot; canyon &quot;)</p>', 'en'));
        self::assertSame('<p>«'.self::NBSP.'Il dit “oui”'.self::NBSP.'»</p>', $this->typographer->fix("<p>&quot; Il dit 'oui' &quot;</p>", 'fr'));
        self::assertSame('<p>Un écran 15" et «'.self::NBSP.'desert'.self::NBSP.'»</p>', $this->typographer->fix('<p>Un écran 15" et " desert "</p>', 'fr'));

        // The inch mark sends the pair to the non-numeric closing pass
        self::assertSame(
            '<p>“The man was 5\'6&quot; and 120 lbs.”</p>',
            $this->typographer->fix("<p>&quot; The man was 5'6&quot; and 120 lbs.&quot;</p>", 'en')
        );
    }

    public function testDimensionAndTrademark(): void
    {
        self::assertSame(
            '<p>Photo 30 × 40, ©'.self::NBSP.'2026 Pushword™®</p>',
            $this->typographer->fix('<p>Photo 30 x 40, (c) 2026 Pushword(tm)(r)</p>', 'en')
        );
        self::assertSame('<p>3× 4 et 5 ×6</p>', $this->typographer->fix('<p>3x 4 et 5 x6</p>', 'fr'));
    }

    public function testLineBreaksAreNotTypographySpaces(): void
    {
        self::assertSame("<p>3 x\n4</p>", $this->typographer->fix("<p>3 x\n4</p>", 'fr'));
        self::assertSame("<p>10\n€</p>", $this->typographer->fix("<p>10\n€</p>", 'fr'));
        self::assertSame("<p>©\n2026</p>", $this->typographer->fix("<p>(c)\n2026</p>", 'fr'));
        self::assertSame("<p>Bonjour\n!</p>", $this->typographer->fix("<p>Bonjour\n!</p>", 'fr'));
        self::assertSame("<p>Bonjour\n: suite</p>", $this->typographer->fix("<p>Bonjour\n: suite</p>", 'fr'));

        self::assertSame("<p>3\t×\t4</p>", $this->typographer->fix("<p>3\tx\t4</p>", 'fr'));
        self::assertSame('<p>10'.self::NBSP.'€</p>', $this->typographer->fix("<p>10\t€</p>", 'fr'));

        // Only horizontal spaces just inside a quotation pair are dropped
        self::assertSame("<p>“\ndesert\n”</p>", $this->typographer->fix("<p>&quot;\ndesert\n&quot;</p>", 'en'));
        self::assertSame("<p>“\ndesert\n”</p>", $this->typographer->fix("<p>\"\ndesert\n\"</p>", 'en'));
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
        $html = '<pre>l\'a : "ok" !</pre><code>l\'b...</code><svg viewBox="0 0 20 20"><path d="m6 8 4-4"/></svg><script>if (a) { alert("l\'z"); }</script><textarea>l\'c...</textarea><math>x'."\u{A0}".'!= y</math>';

        self::assertSame($html, $this->typographer->fix($html, 'fr'));
    }

    public function testRawTextWithAGluedAngleBracketStaysContained(): void
    {
        // `i<n` must not open a pseudo-tag swallowing `</script>` — typography
        // would silently stop for the rest of the document
        self::assertSame(
            '<p>l’un</p><script>for(i=0;i<n;i++)f(i)</script><p>l’autre</p>',
            $this->typographer->fix("<p>l'un</p><script>for(i=0;i<n;i++)f(i)</script><p>l'autre</p>", 'fr')
        );
        self::assertSame(
            '<textarea>if a<b then</textarea><p>l’un</p>',
            $this->typographer->fix("<textarea>if a<b then</textarea><p>l'un</p>", 'fr')
        );
        self::assertSame(
            '<style>a::before{content:"l\'a"}</style><p>l’un</p>',
            $this->typographer->fix('<style>a::before{content:"l\'a"}</style>'."<p>l'un</p>", 'fr')
        );
    }

    public function testRawTextCaseAttributesAndUnclosedForms(): void
    {
        // Case-insensitive, attributes crossed, empty body
        self::assertSame(
            '<SCRIPT TYPE="module" data-x="a>b">if(i<n)f()</SCRIPT><p>l’un</p>',
            $this->typographer->fix('<SCRIPT TYPE="module" data-x="a>b">if(i<n)f()</SCRIPT>'."<p>l'un</p>", 'fr')
        );
        self::assertSame(
            '<script src="/a.js"></script><p>l’un</p>',
            $this->typographer->fix('<script src="/a.js"></script>'."<p>l'un</p>", 'fr')
        );

        // An unclosed script owns the rest of the document (HTML5 raw text)
        $unclosed = "<p>l’un</p><script>if(i<n)f(<p>l'x</p>";
        self::assertSame($unclosed, $this->typographer->fix($unclosed, 'fr'));
    }

    public function testNestedSingleQuotesAndApostrophes(): void
    {
        self::assertSame(
            '<p>He said ‘hello’ to all, rock ‘n’ roll</p>',
            $this->typographer->fix("<p>He said 'hello' to all, rock 'n' roll</p>", 'en')
        );
        self::assertSame('<p>“This ‘magic’ piece”</p>', $this->typographer->fix('<p>"This \'magic\' piece"</p>', 'en'));
        self::assertSame(
            '<p>“This ‘doesn’t fail’ either”</p>',
            $this->typographer->fix('<p>"This \'doesn\'t fail\' either"</p>', 'en')
        );
        self::assertSame('<p>«'.self::NBSP.'Il dit “oui”'.self::NBSP.'»</p>', $this->typographer->fix("<p>&quot;Il dit 'oui'&quot;</p>", 'fr'));

        // Apostrophes and measurement marks are not quotation pairs.
        self::assertSame('<p>It’s 6\' 10" in the 80\'s</p>', $this->typographer->fix('<p>It\'s 6\' 10" in the 80\'s</p>', 'en'));
        self::assertSame("<p>the 80's</p>", $this->typographer->fix("<p>the 80's</p>", 'en'));
    }

    public function testNestedQuoteStylesFollowLocale(): void
    {
        $expectations = [
            'de' => '<p>„Außen ‚innen‘“</p>',
            'de-CH' => '<p>«'.self::NNBSP.'Außen ‹innen›'.self::NNBSP.'»</p>',
            'es' => '<p>«Außen “innen”»</p>',
            'ru' => '<p>«Außen „innen“»</p>',
            'pl' => '<p>„Außen «innen»“</p>',
            'sv' => '<p>”Außen ’innen’”</p>',
        ];

        foreach ($expectations as $locale => $expected) {
            self::assertSame($expected, $this->typographer->fix('<p>"Außen \'innen\'"</p>', $locale), 'Locale '.$locale);
        }
    }

    public function testSmartQuotesDistinguishMeasurements(): void
    {
        self::assertSame(
            '<p>“The man was 5\'6" and 120 lbs.”</p>',
            $this->typographer->fix('<p>"The man was 5\'6" and 120 lbs."</p>', 'en')
        );
        self::assertSame(
            '<p>He said “hi” beside the 27" monitor.</p>',
            $this->typographer->fix('<p>He said "hi" beside the 27" monitor.</p>', 'en')
        );
        self::assertSame(
            '<p>“The man was 5\'6&quot; and 120 lbs.”</p>',
            $this->typographer->fix("<p>&quot;The man was 5'6&quot; and 120 lbs.&quot;</p>", 'en')
        );
    }

    public function testQuotesCrossInlineMarkupButNotBlockBoundaries(): void
    {
        self::assertSame(
            '<p>“hello <em>world</em>”.</p>',
            $this->typographer->fix('<p>"hello <em>world</em>".</p>', 'en')
        );
        self::assertSame(
            '<p>“hello <em>world</em>”.</p>',
            $this->typographer->fix('<p>&quot;hello <em>world</em>&quot;.</p>', 'en')
        );
        self::assertSame(
            '<p>He said ‘hello <b>world</b>’ and left.</p>',
            $this->typographer->fix("<p>He said 'hello <b>world</b>' and left.</p>", 'en')
        );
        self::assertSame(
            '<p>Hello<br>“world”</p>',
            $this->typographer->fix('<p>Hello<br>"world"</p>', 'en')
        );

        $unpaired = '<p>"unpaired</p><p>other"</p>';
        self::assertSame($unpaired, $this->typographer->fix($unpaired, 'en'));
    }

    public function testUnicodeIsNormalizedOutsideProtectedTags(): void
    {
        self::assertSame(
            '<p>élémentaire</p><code>élémentaire</code>',
            $this->typographer->fix("<p>e\u{0301}le\u{0301}mentaire</p><code>e\u{0301}le\u{0301}mentaire</code>", 'fr')
        );
    }

    public function testElisionBeforeAnOpeningTagOrQuoteCurls(): void
    {
        self::assertSame(
            '<p>l’<em>ete</em> a l’«'.self::NBSP.'ile'.self::NBSP.'»</p>',
            $this->typographer->fix("<p>l'<em>ete</em> a l'« ile »</p>", 'fr')
        );
    }

    public function testNumberNeverBreaksFromItsUnit(): void
    {
        self::assertSame(
            '<p>Prix'.self::NBSP.': 10'.self::NBSP.'€, 50'.self::NBSP.'% et 20'.self::NBSP.'°C</p>',
            $this->typographer->fix('<p>Prix : 10 €, 50 % et 20 °C</p>', 'fr')
        );
        self::assertSame('<p>Up 5'.self::NBSP.'% (10'.self::NBSP.'$)</p>', $this->typographer->fix('<p>Up 5 % (10 $)</p>', 'en'));
    }

    public function testNestedProtectedTags(): void
    {
        $html = "<pre><code>l'a...</code></pre><p>l'b</p>";

        self::assertSame('<pre><code>l\'a...</code></pre><p>l’b</p>', $this->typographer->fix($html, 'fr'));
    }

    public function testMarkupBytesArePreserved(): void
    {
        // The comment holds a `>` and a pair of quotes: it must be recognised as a
        // comment, never parsed as a tag
        $html = '<div  class="a"><a href="/l\'apostrophe" title="l\'x">l\'y</a><!-- a > b "c" --><img src="/a.jpg"/></div>';
        $fixed = $this->typographer->fix($html, 'fr');

        self::assertSame('<div  class="a"><a href="/l\'apostrophe" title="l\'x">l’y</a><!-- a > b "c" --><img src="/a.jpg"/></div>', $fixed);
    }

    public function testIdempotent(): void
    {
        $inputs = [
            ['fr', "<p>Il a dit &quot;bonjour&quot; a l'ami : vrai ! 30 x 40 (c) 2026...</p>"],
            ['de-CH', '<p>Er sagte &quot;hallo&quot; ! 3x4</p>'],
            ['en', '<p>He said &quot;hi&quot; : yes !...</p>'],
            ['fr', '<div data-arrow="<div class=\'s\'>x</div>"><p>l\'ete &quot;a&quot;</p></div>'],
            ['fr', "<p>10 € et l'<em>ete</em> 50 %</p>"],
            ['fr', "<p>l'un</p><script>if(i<n)f()</script><p>l'autre</p>"],
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

    /** A `data-*` attribute carrying a whole HTML fragment stays one tag. */
    public function testMarkupInsideAttributeValue(): void
    {
        $html = '<div class="prose" data-arrow="<div class=\'s\'>x</div><div class=\'g\'></div>" data-fadeout="<div class=\'f\'></div>">'
            ."<p>l'ete</p></div>";

        self::assertSame(
            '<div class="prose" data-arrow="<div class=\'s\'>x</div><div class=\'g\'></div>" data-fadeout="<div class=\'f\'></div>"><p>l’ete</p></div>',
            $this->typographer->fix($html, 'fr')
        );

        // Same, with the quote flavours swapped
        self::assertSame(
            '<div data-x=\'<p class="a">y</p>\'><p>l’ete</p></div>',
            $this->typographer->fix('<div data-x=\'<p class="a">y</p>\'>'."<p>l'ete</p></div>", 'fr')
        );
    }

    public function testLoneAngleBracketInTextIsStillTypographed(): void
    {
        self::assertSame('<p>a < b, l’ete</p>', $this->typographer->fix("<p>a < b, l'ete</p>", 'fr'));
        self::assertSame(
            '<p>je <3 les «'.self::NBSP.'chats'.self::NBSP.'»</p>',
            $this->typographer->fix('<p>je <3 les "chats"</p>', 'fr')
        );
    }
}
