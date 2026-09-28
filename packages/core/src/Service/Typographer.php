<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use Exception;
use Normalizer;

/**
 * Locale-aware typographic fixer, a Pushword port of JoliTypo rules
 * (Ellipsis, Dimension, SmartQuotes, CurlyQuote, Trademark, punctuation
 * spacing — deliberately no Dash and no Hyphen).
 *
 * Unlike JoliTypo it never parses the document: markup is split out with a
 * regex and passed through byte-identical, rules only touch the text between
 * tags. libxml's HTML4 round-trip would lowercase SVG attributes (viewBox),
 * unfold self-closing SVG tags and re-encode UTF-8 as entities.
 */
final class Typographer
{
    private const string NBSP = "\u{00A0}";

    private const string NNBSP = "\u{202F}";

    /** Every horizontal space flavour plus soft hyphen, for use inside a regex character class (/u). */
    private const string SPACES = '\x{00AD}\h';

    /** Tags whose content must stay untouched. */
    private const array PROTECTED_TAGS = ['pre' => true, 'code' => true, 'script' => true, 'style' => true, 'svg' => true, 'math' => true, 'textarea' => true, 'template' => true];

    /** Block boundaries across which quotation pairs must never be inferred. */
    private const array QUOTE_BOUNDARY_TAGS = [
        'address' => true, 'article' => true, 'aside' => true, 'blockquote' => true, 'body' => true,
        'dd' => true, 'details' => true, 'dialog' => true, 'div' => true, 'dl' => true, 'dt' => true,
        'fieldset' => true, 'figcaption' => true, 'figure' => true, 'footer' => true, 'form' => true,
        'h1' => true, 'h2' => true, 'h3' => true, 'h4' => true, 'h5' => true, 'h6' => true,
        'header' => true, 'hgroup' => true, 'hr' => true, 'html' => true, 'li' => true, 'main' => true,
        'nav' => true, 'ol' => true, 'p' => true, 'section' => true, 'summary' => true, 'table' => true,
        'tbody' => true, 'td' => true, 'tfoot' => true, 'th' => true, 'thead' => true, 'tr' => true, 'ul' => true,
    ];

    /**
     * The attribute run of a tag. Quoted values are consumed whole because
     * they may themselves contain `>` (templates pass entire HTML fragments
     * through `data-*` attributes); stopping at the first `>` would leak the
     * rest of the tag into the text stream, where SmartQuotes turns the
     * remaining attribute delimiters into guillemets and breaks the markup.
     */
    private const string ATTRS = '[^>"\']*+(?:(?:"[^"]*+"|\'[^\']*+\')[^>"\']*+)*+';

    /**
     * A comment, a raw-text element consumed whole, or a tag. Script, style
     * and textarea hold raw text whose `<` is content (`i<n` in an inline
     * script): each is taken up to its closing tag, otherwise the pseudo-tag
     * opened by that `<` would swallow the closing tag and leave the rest of
     * the document protected.
     */
    private const string MARKUP = '#(<!--.*?-->'
        .'|(?i:<script\b'.self::ATTRS.'>.*?</script\s*+>)'
        .'|(?i:<style\b'.self::ATTRS.'>.*?</style\s*+>)'
        .'|(?i:<textarea\b'.self::ATTRS.'>.*?</textarea\s*+>)'
        .'|<[a-zA-Z/!?]'.self::ATTRS.'>)#s';

    /** @var array<string, array{string, string, string, string}> opening, opening suffix, closing, closing prefix */
    private const array QUOTE_STYLES = [
        'double' => ['“', '', '”', ''],
        'guillemets' => ['«', '', '»', ''],
        'guillemetsFr' => ['«', self::NBSP, '»', self::NBSP],
        'german' => ['„', '', '“', ''],
        'finnish' => ['”', '', '”', ''],
        'singleDouble' => ['‘', '', '’', ''],
        'singleGuillemets' => ['‹', '', '›', ''],
        'singleGerman' => ['‚', '', '‘', ''],
        'singleFinnish' => ['’', '', '’', ''],
    ];

    /** Same locale → quote style map as JoliTypo's LocaleConfig (language codes, plus two locale overrides). */
    private const array LOCALE_QUOTE_STYLE = [
        'en' => 'double', 'af' => 'double', 'ar' => 'double', 'eo' => 'double', 'id' => 'double', 'ga' => 'double', 'ko' => 'double', 'br' => 'double', 'th' => 'double', 'tr' => 'double', 'vi' => 'double', 'nl' => 'double', 'pt-br' => 'double',
        'hy' => 'guillemets', 'az' => 'guillemets', 'eu' => 'guillemets', 'be' => 'guillemets', 'ca' => 'guillemets', 'el' => 'guillemets', 'it' => 'guillemets', 'no' => 'guillemets', 'nb' => 'guillemets', 'nn' => 'guillemets', 'fa' => 'guillemets', 'lv' => 'guillemets', 'pt' => 'guillemets', 'ru' => 'guillemets', 'es' => 'guillemets', 'uk' => 'guillemets', 'da' => 'guillemets', 'de-ch' => 'guillemets',
        'fr' => 'guillemetsFr',
        'de' => 'german', 'ka' => 'german', 'cs' => 'german', 'et' => 'german', 'is' => 'german', 'lt' => 'german', 'mk' => 'german', 'ro' => 'german', 'sk' => 'german', 'sl' => 'german', 'pl' => 'german', 'hr' => 'german', 'sr' => 'german', 'bg' => 'german', 'hu' => 'german',
        'fi' => 'finnish', 'sv' => 'finnish', 'bs' => 'finnish',
    ];

    /** Locale overrides for second-level quotation marks. Other locales derive them from their primary style. */
    private const array LOCALE_NESTED_QUOTE_STYLE = [
        'fr' => 'double', 'ca' => 'double', 'el' => 'double', 'it' => 'double', 'pt' => 'double', 'es' => 'double',
        'be' => 'german', 'ru' => 'german', 'uk' => 'german',
        'pl' => 'guillemets', 'ro' => 'guillemets',
        'pt-br' => 'singleDouble',
    ];

    private const array NESTED_QUOTE_STYLE_FROM_PRIMARY = [
        'double' => 'singleDouble',
        'guillemets' => 'singleGuillemets',
        'guillemetsFr' => 'singleGuillemets',
        'german' => 'singleGerman',
        'finnish' => 'singleFinnish',
    ];

    private const string QUOTE_SPACES = '\s\x{00A0}\x{202F}';

    private const string DOUBLE_QUOTES = '"“”„«»';

    private const string BEFORE_SINGLE_OPENING_CHARS = self::QUOTE_SPACES.'(\['.self::DOUBLE_QUOTES;

    private const string AFTER_SINGLE_CLOSING_CHARS = self::QUOTE_SPACES.'.,;:!?)\]'.self::DOUBLE_QUOTES;

    public function fix(string $text, string $locale): string
    {
        if ('' === trim($text)) {
            return $text;
        }

        $locale = strtolower(str_replace('_', '-', $locale));

        if (! str_contains($text, '<')) {
            return $this->fixRun([[false, $text]], $locale);
        }

        // Without NO_EMPTY the split alternates strictly text, markup, text…,
        // so the odd indexes are the captured markup — a `<` opening a text
        // part (`a < b`, `<3`) must not be mistaken for a tag.
        $parts = preg_split(self::MARKUP, $text, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: throw new Exception();
        $protectedDepth = 0;
        $fixed = '';
        /** @var list<array{bool, string}> $run */
        $run = [];

        foreach ($parts as $i => $part) {
            $isMarkup = 1 === $i % 2;

            if ($isMarkup) {
                $isTag = 1 === preg_match('#^<(/?)([a-zA-Z0-9-]+)#', $part, $match);
                $tag = $isTag ? strtolower($match[2]) : '';
                $isProtectedTag = $isTag && isset(self::PROTECTED_TAGS[$tag]);

                if ($isProtectedTag || 0 < $protectedDepth) {
                    if (0 === $protectedDepth) {
                        $fixed .= $this->fixRun($run, $locale);
                        $run = [];
                    }

                    $fixed .= $part;

                    if (! $isProtectedTag) {
                        continue;
                    }

                    if ('/' === $match[1]) {
                        $protectedDepth = max(0, $protectedDepth - 1);
                    } elseif (! str_ends_with($part, '/>') && 1 !== preg_match('#</'.$match[2].'\s*+>$#i', $part)) {
                        // A raw-text element consumed whole opens nothing
                        ++$protectedDepth;
                    }

                    continue;
                }

                if ($isTag && isset(self::QUOTE_BOUNDARY_TAGS[$tag])) {
                    $fixed .= $this->fixRun($run, $locale).$part;
                    $run = [];

                    continue;
                }

                $run[] = [true, $part];

                continue;
            }

            if (0 < $protectedDepth) {
                $fixed .= $part;

                continue;
            }

            $run[] = [false, $part];
        }

        return $fixed.$this->fixRun($run, $locale);
    }

    /**
     * @param list<array{bool, string}> $parts [is markup, value]
     */
    private function fixRun(array $parts, string $locale): string
    {
        if ([] === $parts) {
            return '';
        }

        $marker = $this->unusedPrivateCharacter(implode('', array_column($parts, 1)));

        $logicalText = '';
        $markup = [];
        foreach ($parts as [$isMarkup, $part]) {
            if ($isMarkup) {
                $markup[] = $part;
                $logicalText .= $marker;

                continue;
            }

            $logicalText .= $this->applyPreQuoteRules($part);
        }

        $logicalText = $this->smartQuotes($logicalText, $locale, $marker);
        $markupIndex = 0;
        $text = preg_replace_callback(
            '#'.preg_quote($marker, '#').'#u',
            static function () use ($markup, &$markupIndex): string {
                return $markup[$markupIndex++];
            },
            $logicalText
        ) ?? throw new Exception();
        $fixedParts = preg_split(self::MARKUP, $text, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: throw new Exception();

        foreach ($fixedParts as $i => $part) {
            if (0 === $i % 2 && '' !== trim($part)) {
                $fixedParts[$i] = $this->applyPostQuoteRules($part, $locale, 1 === preg_match('#^<[a-zA-Z]#', $fixedParts[$i + 1] ?? ''));
            }
        }

        return implode('', $fixedParts);
    }

    private function applyPreQuoteRules(string $text): string
    {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);
        if (is_string($normalized)) {
            $text = $normalized;
        }

        if (str_contains($text, '.')) {
            $text = $this->replace('#\.{3,}#', '…', $text);
        }

        // Dimension: 3x4 → 3×4
        if (str_contains($text, 'x')) {
            return $this->replace('#(\d+(?:["\']|&quot;)?)(['.self::SPACES.'])?x(['.self::SPACES.'])?(?=\d)#u', '$1$2×$3', $text);
        }

        return $text;
    }

    private function applyPostQuoteRules(string $text, string $locale, bool $beforeOpeningTag): string
    {
        // CurlyQuote: apostrophe between letters (JoliTypo's in-word rule).
        // A letter-before-only rule would curl just the closing side of a
        // quotation pair ('hello' → 'hello’). An elision may also run into an
        // opening quote (l'« île ») or, at the end of a text part, into an
        // inline tag (l'<em>été</em>).
        if (str_contains($text, "'")) {
            $text = $this->replace('#(\p{L})\'(?=[\p{L}«“„]'.($beforeOpeningTag ? '|$' : '').')#u', '$1’', $text);
        }

        if (str_contains($text, '(')) {
            $text = $this->trademark($text);
        }

        return $this->spacing($text, $locale);
    }

    private function smartQuotes(string $text, string $locale, string $markupMarker): string
    {
        $language = explode('-', $locale)[0];
        $style = self::LOCALE_QUOTE_STYLE[$locale] ?? self::LOCALE_QUOTE_STYLE[$language] ?? 'double';

        [$opening, $openingSuffix, $closing, $closingPrefix] = self::QUOTE_STYLES[$style];

        // Twice, because in rendered HTML the double quote is usually the
        // &quot; entity (CommonMark and Twig both escape it) but raw template
        // text keeps the plain character.
        $text = $this->replaceQuotePairs($text, '&quot;', $opening.$openingSuffix, $closingPrefix.$closing, $markupMarker);
        $text = $this->replaceQuotePairs($text, '"', $opening.$openingSuffix, $closingPrefix.$closing, $markupMarker);

        if (! str_contains($text, "'")) {
            return $text;
        }

        $nestedStyle = self::LOCALE_NESTED_QUOTE_STYLE[$locale]
            ?? self::LOCALE_NESTED_QUOTE_STYLE[$language]
            ?? self::NESTED_QUOTE_STYLE_FROM_PRIMARY[$style];
        [$nestedOpening, $nestedOpeningSuffix, $nestedClosing, $nestedClosingPrefix] = self::QUOTE_STYLES[$nestedStyle];
        $marker = preg_quote($markupMarker, '#');
        $beforeOpening = self::BEFORE_SINGLE_OPENING_CHARS;
        $afterClosing = self::AFTER_SINGLE_CLOSING_CHARS.$marker;
        $singleOpening = '(^|['.$beforeOpening.$marker."])'";
        $singleClosing = "'(?=[".$afterClosing.']|$)';
        $singleContent = '(?:'.$marker."|'(?![".$afterClosing.']|$)|['.$beforeOpening."](?!')|[^".$beforeOpening.$marker."'])";

        return $this->replace(
            '#'.$singleOpening.'('.$singleContent.'+)'.$singleClosing.'#u',
            '${1}'.$nestedOpening.$nestedOpeningSuffix.'${2}'.$nestedClosingPrefix.$nestedClosing,
            $text
        );
    }

    /**
     * Replace paired double quotes while leaving inch and second marks after a digit alone.
     * The three passes mirror JoliTypo: unambiguous pair, non-numeric closing, then fallback.
     */
    private function replaceQuotePairs(string $text, string $quote, string $opening, string $closing, string $markupMarker): string
    {
        if (! str_contains($text, $quote)) {
            return $text;
        }

        $sentinel = $this->unusedPrivateCharacter($text);
        $text = str_replace($quote, $sentinel, $text);
        $q = preg_quote($sentinel, '#');
        $marker = preg_quote($markupMarker, '#');
        $notQuote = '(?:(?!'.$q.')[\s\S])';
        $replacement = '$1'.$opening.'$2'.$closing;

        $text = $this->replace('#(^|[\s(]|'.$marker.')'.$q.'('.$notQuote.'+)'.$q.'(?='.$notQuote.'*(?:$|[\s(]'.$q.'))#imu', $replacement, $text);
        $text = $this->replace('#(^|[\s(]|'.$marker.')'.$q.'((?:'.$notQuote.'|(?<=\d)'.$q.')+?)(?<!\d)'.$q.'#imu', $replacement, $text);
        $text = $this->replace('#(^|[\s(]|'.$marker.')'.$q.'('.$notQuote.'+)'.$q.'#imu', $replacement, $text);

        return str_replace($sentinel, $quote, $text);
    }

    private function unusedPrivateCharacter(string $text): string
    {
        for ($codepoint = 0xE100; $codepoint <= 0xF8FF; ++$codepoint) {
            $candidate = html_entity_decode('&#'.$codepoint.';', \ENT_NOQUOTES, 'UTF-8');
            if (! str_contains($text, $candidate)) {
                return $candidate;
            }
        }

        throw new Exception('Unable to reserve a typography placeholder.');
    }

    private function trademark(string $text): string
    {
        $text = $this->replace('#\(tm\)#i', '™', $text);
        $text = $this->replace('#\(c\)['.self::SPACES.']([0-9]+)#iu', '©'.self::NBSP.'$1', $text);
        $text = $this->replace('#\(c\)#i', '©', $text);

        return $this->replace('#\(r\)#i', '®', $text);
    }

    private function spacing(string $text, string $locale): string
    {
        // A figure never breaks from its unit or currency symbol (the block
        // editor's old fixer rule, applied at render for every locale)
        if (false !== strpbrk($text, '0123456789º')) {
            $text = $this->replace('#([\dº])['.self::SPACES.']+([º°%Ω฿₵¢₡$₫֏€ƒ₲₴₭£₤₺₦₨₱៛₹₪৳₸₮₩¥])#u', '$1'.self::NBSP.'$2', $text);
        }

        // Canadian French follows the English convention: no space before punctuation
        if (('fr' === $locale || str_starts_with($locale, 'fr-')) && 'fr-ca' !== $locale) {
            if (str_contains($text, ':')) {
                $text = $this->replace('#['.self::SPACES.']+(:)#mu', self::NBSP.'$1', $text);
            }

            if (false !== strpbrk($text, ';!?')) {
                $text = $this->replace('#['.self::SPACES.']+([;!?])#mu', self::NNBSP.'$1', $text);
            }

            if (str_contains($text, '«')) {
                $text = $this->replace('#«['.self::SPACES.']?#u', '«'.self::NBSP, $text);
            }

            if (str_contains($text, '»')) {
                return $this->replace('#['.self::SPACES.']?»#u', self::NBSP.'»', $text);
            }

            return $text;
        }

        if ('de-ch' === $locale) {
            if (str_contains($text, '«')) {
                $text = $this->replace('#«['.self::SPACES.']?#u', '«'.self::NNBSP, $text);
            }

            if (str_contains($text, '»')) {
                $text = $this->replace('#['.self::SPACES.']?»#u', self::NNBSP.'»', $text);
            }
        }

        // Everyone else: no space before high punctuation (":" spared when
        // starting a URL or a time)
        if (str_contains($text, ':')) {
            $text = $this->replace('#([^'.self::SPACES.':])['.self::SPACES.']+(:)(?![/\d])#mu', '$1$2', $text);
        }

        if (false !== strpbrk($text, ';!?')) {
            return $this->replace('#([^'.self::SPACES.'])['.self::SPACES.']+([;!?])#mu', '$1$2', $text);
        }

        return $text;
    }

    private function replace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? throw new Exception();
    }
}
