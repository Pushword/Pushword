<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

final class HtmlEncryptedLink extends EncryptedLink
{
    const HTML_REGEX = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1(?:[^>]*?\s+)?rel=(["\'])encrypt\1(?:[^>]*?\s+)?>(((?!<\/a>).)*)<\/a>/i';
    const HTML_REGEX_HREF_KEY = 2;
    const HTML_REGEX_ANCHOR_KEY = 4;

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

        return $this->replaceEncryptedLink($body, $matches, self::HTML_REGEX_HREF_KEY, self::HTML_REGEX_ANCHOR_KEY);
    }
}
