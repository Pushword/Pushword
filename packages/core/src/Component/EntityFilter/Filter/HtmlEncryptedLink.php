<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

final class HtmlEncryptedLink extends EncryptedLink
{
    /**
     * @var string
     */
    public const HTML_REGEX = '/(<a\s+(?:[^>]*?\s+)?href=(["\'])(?P<href>((?!\2).)*)\2(?:[^>]*?\s+)?rel=(["\'])encrypt\5(?:[^>]*?\s+)?>(?P<anchor>((?!<\/a>).)*)<\/a>)/i';

    /**
     * @var string
     */
    public const HTML_REGEX_HREF_KEY = 'href';

    /**
     * @var string
     */
    public const HTML_REGEX_ANCHOR_KEY = 'anchor';

    public function convertEncryptedLink(string $body): string
    {
        return $this->convertHtmlRelEncryptedLink($body);
    }

    public function convertHtmlRelEncryptedLink(string $body): string
    {
        \Safe\preg_match_all(self::HTML_REGEX, $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        return $this->replaceRelEncryptedLink($body, $matches, self::HTML_REGEX_HREF_KEY, self::HTML_REGEX_ANCHOR_KEY);
    }

    private function extractClass(string $openingTag): string
    {
        return 1 === preg_match('/class=\"([^"]*)\"/i', $openingTag, $match) ? $match[1] : '';
    }

    /**
     * @param array<(string|int), array<int, string>> $matches
     */
    private function replaceRelEncryptedLink(string $body, array $matches, string $hrefKey = '2', string $anchorKey = '1'): string
    {
        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $attr = $this->extractClass($matches[1][$k]);
            $attr = '' !== $attr ? ['class' => $attr] : [];
            $link = $this->renderLink($matches[$anchorKey][$k], $matches[$hrefKey][$k], $attr);
            $body = str_replace($matches[0][$k],  $link, $body);
        }

        return $body;
    }
}
