<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

final class HtmlEncryptedLink extends EncryptedLink
{
    public const HTML_REGEX = '/(<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\2(?:[^>]*?\s+)?rel=(["\'])encrypt\4(?:[^>]*?\s+)?>)(((?!<\/a>).)*)<\/a>/i';

    public const HTML_REGEX_HREF_KEY = 3;

    public const HTML_REGEX_ANCHOR_KEY = 5;

    public function convertEncryptedLink($body): string
    {
        return $this->convertHtmlRelEncryptedLink($body);
    }

    public function convertHtmlRelEncryptedLink(string $body): string
    {
        preg_match_all(self::HTML_REGEX, $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        return $this->replaceRelEncryptedLink($body, $matches, self::HTML_REGEX_HREF_KEY, self::HTML_REGEX_ANCHOR_KEY);
    }

    private function extractClass(string $openingTag): string
    {
        return preg_match('/class=\"([^"]*)\"/i', $openingTag, $match) ? $match[1] : '';
    }

    private function replaceRelEncryptedLink(string $body, array $matches, int $hrefKey = 2, int $anchorKey = 1): string
    {
        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $attr = $this->extractClass($matches[1][$k]);
            $attr = $attr ? ['class' => $attr] : [];
            $link = $this->renderLink($matches[$anchorKey][$k], $matches[$hrefKey][$k], $attr);
            $body = str_replace($matches[0][$k], $link, $body);
        }

        return $body;
    }
}
