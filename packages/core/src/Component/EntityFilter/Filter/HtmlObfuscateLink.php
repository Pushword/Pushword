<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Exception;

use function Safe\preg_match_all;

final class HtmlObfuscateLink extends ObfuscateLink
{
    /**
     * @var string
     *             Attr Regex :     (?<=href=)((?P<hrefQuote>'|")(?P<href>.*?)(?P=hrefQuote)|(?P<href>[^"'>][^> \r\n\t\f\v]*))
     *             Attr Regex :     (?<=href=)((?P<hrefQuote>'|")(?P<href1>(?:(?!(?P=hrefQuote)).)*)(?P=hrefQuote)|(?P<href>[^"'>][^> \r\n\t\f\v]*))
     *             Attr Regex :     (?<=rel=)((?P<relQuote>'|")obfuscate(?P=relQuote)|obfuscate)
     *             Between Attr :   \s+(?:[^>]*\s{0,1})
     *             or between Tag and attr
     *             End Attr :       (?:[^>]*)?
     *             /(<a\s+(?:[^>]*\s{0,1})(HREF-ESPACE-ENCRYPT|ENCRYPT-ESPACE-HREF)(?:[^>]*)?>(?P<anchor>((?!<\/a>).)*)<\/a>)/iJ
     *             /(<a\s+(?:[^>]*\s{0,1})((?<=href=)((?P<hrefQuote>'|")(?P<href1>(?:(?!(?P=hrefQuote)).)*)(?P=hrefQuote)|(?P<href>[^"'>][^> \r\n\t\f\v]*))\s+(?:[^>]*\s{0,1})(?<=rel=)((?P<relQuote>'|")obfuscate(?P=relQuote)|obfuscate)|(?<=rel=)((?P<relQuote>'|")encrypt(?P=relQuote)|encrypt)\s+(?:[^>]*\s{0,1})(?<=href=)((?P<hrefQuote>'|")(?P<href1>(?:(?!(?P=hrefQuote)).)*)(?P=hrefQuote)|(?P<href>[^"'>][^> \r\n\t\f\v]*)))(?:[^>]*)?>(?P<anchor>((?!<\/a>).)*)<\/a>)/iJ
     */
    public const HTML_REGEX = '/(<a\s+(?:[^>]*\s{0,1})((?<=href=)((?P<hrefQuote>\'|")(?P<href1>(?:(?!(?P=hrefQuote)).)*)(?P=hrefQuote)|(?P<href2>[^"\'>][^> \r\n\t\f\v]*))\s+(?:[^>]*\s{0,1})(?<=rel=)((?P<relQuote>\'|")(encrypt|obfuscate)(?P=relQuote)|(encrypt|obfuscate))|(?<=rel=)((?P<relQuote>\'|")(encrypt|obfuscate)(?P=relQuote)|(encrypt|obfuscate))\s+(?:[^>]*\s{0,1})(?<=href=)((?P<hrefQuote>\'|")(?P<href3>(?:(?!(?P=hrefQuote)).)*)(?P=hrefQuote)|(?P<href4>[^"\'>][^> \r\n\t\f\v]*)))(?:[^>]*)?>(?P<anchor>((?!<\/a>).)*)<\/a>)/iJ';

    /** @var string */
    public const HTML_REGEX_HREF_KEY = 'href';

    /**
     * @var string
     */
    public const HTML_REGEX_ANCHOR_KEY = 'anchor';

    public function convertObfuscateLink(string $body): string
    {
        return $this->convertHtmlRelObfuscateLink($body);
    }

    public function convertHtmlRelObfuscateLink(string $body): string
    {
        preg_match_all(self::HTML_REGEX, $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        /** @var array<(string|int), array<int, string>> $matches */
        return $this->replaceRelObfuscateLink($body, $matches);
    }

    private function extractClass(string $openingTag): string
    {
        return 1 === preg_match('#class=\"([^"]*)\"#i', $openingTag, $match) ? $match[1] : '';
    }

    /**
     * @param array<(string|int), array<int, string>> $matches
     */
    private function replaceRelObfuscateLink(string $body, array $matches): string
    {
        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $attr = $this->extractClass($matches[1][$k]);
            $attr = '' !== $attr ? ['class' => $attr] : [];
            $link = $this->linkProvider->renderLink(
                $matches[self::HTML_REGEX_ANCHOR_KEY][$k],
                $this->getHrefValue($matches, $k),
                $attr
            );
            $body = str_replace($matches[0][$k],  $link, $body);
        }

        return $body;
    }

    /**
     * @param array<(string|int), array<int, string>> $matches
     */
    private function getHrefValue(array $matches, int $k): string
    {
        for ($i = 1; $i < 5; ++$i) {
            if ('' !== $matches[self::HTML_REGEX_HREF_KEY.$i][$k]) {
                return $matches[self::HTML_REGEX_HREF_KEY.$i][$k];
            }
        }

        throw new Exception();
    }
}
