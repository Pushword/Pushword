<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Twig\EncryptedLinkTwigTrait;

class EncryptedLink extends AbstractFilter
{
    use EncryptedLinkTwigTrait;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    const HTML_REGEX = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1(?:[^>]*?\s+)?rel=(["\'])encrypt\1(?:[^>]*?\s+)?>(((?!<\/a>).)*)<\/a>/i';
    const HTML_REGEX_HREF_KEY = 2;
    const HTML_REGEX_ANCHOR_KEY = 4;

    /**
     * @return string
     */
    public function apply($string)
    {
        $string = $this->convertEncryptedLink($string);

        return $string;
    }

    public function convertEncryptedLink($body): string
    {
        return $this->convertMarkdownEncryptedLink($body);
    }

    public function convertMarkdownEncryptedLink(string $body): string
    {
        preg_match_all('/(?:#\[(.*?)\]\((.*?)\))({(?:([#.][-_:a-zA-Z0-9 ]+)+)\})?/', $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        return $this->replaceEncryptedLink($body, $matches);
    }

    protected function replaceEncryptedLink(string $body, array $matches, int $hrefKey = 2, int $anchorKey = 1): string
    {
        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $attr = $matches[3][$k] ?? null;
            $attr = $attr ? [('#' == $attr ? 'id' : 'class') => substr($attr, 1)] : [];
            $link = $this->renderEncryptedLink($matches[$anchorKey][$k], $matches[$hrefKey][$k], $attr);
            $body = str_replace($matches[0][$k],  $link, $body);
        }

        return $body;
    }
}
