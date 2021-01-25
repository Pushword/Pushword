<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Twig\EncryptedLinkTwigTrait;

class EncryptedLink extends AbstractFilter
{
    use EncryptedLinkTwigTrait;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    /**
     * @return string
     */
    public function apply($string)
    {
        $string = $this->convertEncryptedLink($string);

        return $string;
    }

    public function convertEncryptedLink($body)
    {
        return $this->convertMarkdownEncryptedLink($body);
    }

    public function convertMarkdownEncryptedLink(string $body): string
    {
        preg_match_all('/(?:#\[(.*?)\]\((.*?)\))({(?:([#.][-_:a-zA-Z0-9 ]+)+)\})?/', $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $attr = $matches[3][$k] ?? null;
            $attr = $attr ? [('#' == $attr ? 'id' : 'class') => substr($attr, 1)] : [];
            $link = $this->renderEncryptedLink($matches[1][$k], $matches[2][$k], $attr);
            $body = str_replace($matches[0][$k],  $link, $body);
        }

        return $body;
    }
}
