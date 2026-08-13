<?php

namespace Pushword\Core\Service;

use Exception;

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

    /** Every space flavour, for use inside a regex character class (/u). */
    private const string SPACES = '\x{202F}\x{2009}\x{00AD}\x{00A0}\s';

    /** Tags whose content must stay untouched. */
    private const array PROTECTED_TAGS = ['pre' => true, 'code' => true, 'script' => true, 'style' => true, 'svg' => true, 'math' => true, 'textarea' => true, 'template' => true];

    /**
     * A comment, or a tag. Quoted attribute values are consumed whole because
     * they may themselves contain `>` (templates pass entire HTML fragments
     * through `data-*` attributes); stopping at the first `>` would leak the
     * rest of the tag into the text stream, where SmartQuotes turns the
     * remaining attribute delimiters into guillemets and breaks the markup.
     */
    private const string MARKUP = '#(<!--.*?-->|<[a-zA-Z/!?][^>"\']*+(?:(?:"[^"]*+"|\'[^\']*+\')[^>"\']*+)*+>)#s';

    /** @var array<string, array{string, string, string, string}> opening, opening suffix, closing, closing prefix */
    private const array QUOTE_STYLES = [
        'double' => ['“', '', '”', ''],
        'guillemets' => ['«', '', '»', ''],
        'guillemetsFr' => ['«', self::NBSP, '»', self::NBSP],
        'german' => ['„', '', '“', ''],
        'finnish' => ['”', '', '”', ''],
    ];

    /** Same locale → quote style map as JoliTypo's LocaleConfig (language codes, plus two locale overrides). */
    private const array LOCALE_QUOTE_STYLE = [
        'en' => 'double', 'af' => 'double', 'ar' => 'double', 'eo' => 'double', 'id' => 'double', 'ga' => 'double', 'ko' => 'double', 'br' => 'double', 'th' => 'double', 'tr' => 'double', 'vi' => 'double', 'nl' => 'double', 'pt-br' => 'double',
        'hy' => 'guillemets', 'az' => 'guillemets', 'eu' => 'guillemets', 'be' => 'guillemets', 'ca' => 'guillemets', 'el' => 'guillemets', 'it' => 'guillemets', 'no' => 'guillemets', 'nb' => 'guillemets', 'nn' => 'guillemets', 'fa' => 'guillemets', 'lv' => 'guillemets', 'pt' => 'guillemets', 'ru' => 'guillemets', 'es' => 'guillemets', 'uk' => 'guillemets', 'da' => 'guillemets', 'de-ch' => 'guillemets',
        'fr' => 'guillemetsFr',
        'de' => 'german', 'ka' => 'german', 'cs' => 'german', 'et' => 'german', 'is' => 'german', 'lt' => 'german', 'mk' => 'german', 'ro' => 'german', 'sk' => 'german', 'sl' => 'german', 'pl' => 'german', 'hr' => 'german', 'sr' => 'german', 'bg' => 'german', 'hu' => 'german',
        'fi' => 'finnish', 'sv' => 'finnish', 'bs' => 'finnish',
    ];

    public function fix(string $text, string $locale): string
    {
        if ('' === trim($text)) {
            return $text;
        }

        $locale = strtolower(str_replace('_', '-', $locale));

        if (! str_contains($text, '<')) {
            return $this->applyRules($text, $locale);
        }

        // Without NO_EMPTY the split alternates strictly text, markup, text…,
        // so the odd indexes are the captured markup — a `<` opening a text
        // part (`a < b`, `<3`) must not be mistaken for a tag.
        $parts = preg_split(self::MARKUP, $text, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: throw new Exception();
        $protectedDepth = 0;
        $fixed = '';

        foreach ($parts as $i => $part) {
            $isMarkup = 1 === $i % 2;

            if ($isMarkup) {
                if (1 === preg_match('#^<(/?)([a-zA-Z0-9-]+)#', $part, $match) && isset(self::PROTECTED_TAGS[strtolower($match[2])])) {
                    if ('/' === $match[1]) {
                        $protectedDepth = max(0, $protectedDepth - 1);
                    } elseif (! str_ends_with($part, '/>')) {
                        ++$protectedDepth;
                    }
                }

                $fixed .= $part;

                continue;
            }

            $fixed .= 0 === $protectedDepth && '' !== trim($part) ? $this->applyRules($part, $locale) : $part;
        }

        return $fixed;
    }

    private function applyRules(string $text, string $locale): string
    {
        $text = $this->replace('#\.{3,}#', '…', $text);

        // Dimension: 3x4 → 3×4
        $text = $this->replace('#(\d+(?:["\']|&quot;)?)(['.self::SPACES.'])?x(['.self::SPACES.'])?(?=\d)#u', '$1$2×$2', $text);

        $text = $this->smartQuotes($text, $locale);

        // CurlyQuote: apostrophe after a letter
        $text = $this->replace('#(\p{L})\'#u', '$1’', $text);

        $text = $this->trademark($text);

        return $this->spacing($text, $locale);
    }

    private function smartQuotes(string $text, string $locale): string
    {
        $style = self::LOCALE_QUOTE_STYLE[$locale]
            ?? self::LOCALE_QUOTE_STYLE[explode('-', $locale)[0]]
            ?? 'double';

        [$opening, $openingSuffix, $closing, $closingPrefix] = self::QUOTE_STYLES[$style];

        // Twice, because in rendered HTML the double quote is usually the
        // &quot; entity (CommonMark and Twig both escape it) but raw template
        // text keeps the plain character.
        $text = $this->replace(
            '#(^|[\s(])&quot;((?:(?!&quot;)[^"])+)&quot;#mu',
            '$1'.$opening.$openingSuffix.'$2'.$closingPrefix.$closing,
            $text
        );

        return $this->replace(
            '#(^|[\s(])"([^"]+)"#mu',
            '$1'.$opening.$openingSuffix.'$2'.$closingPrefix.$closing,
            $text
        );
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
        // Canadian French follows the English convention: no space before punctuation
        if (('fr' === $locale || str_starts_with($locale, 'fr-')) && 'fr-ca' !== $locale) {
            $text = $this->replace('#['.self::SPACES.']+(:)#mu', self::NBSP.'$1', $text);
            $text = $this->replace('#['.self::SPACES.']+([;!?])#mu', self::NNBSP.'$1', $text);
            $text = $this->replace('#«['.self::SPACES.']?#u', '«'.self::NBSP, $text);

            return $this->replace('#['.self::SPACES.']?»#u', self::NBSP.'»', $text);
        }

        if ('de-ch' === $locale) {
            $text = $this->replace('#«['.self::SPACES.']?#u', '«'.self::NNBSP, $text);
            $text = $this->replace('#['.self::SPACES.']?»#u', self::NNBSP.'»', $text);
        }

        // Everyone else: no space before high punctuation (":" spared when
        // starting a URL or a time)
        $text = $this->replace('#([^'.self::SPACES.':])['.self::SPACES.']+(:)(?![/\d])#mu', '$1$2', $text);

        return $this->replace('#([^'.self::SPACES.'])['.self::SPACES.']+([;!?])#mu', '$1$2', $text);
    }

    private function replace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? throw new Exception();
    }
}
