<?php

namespace Pushword\Core\Component\Filter\Filters;

use Pushword\Core\Twig\EncryptedLinkTwigTrait;

class EncryptedLink extends ShortCode
{
    use EncryptedLinkTwigTrait;

    public function apply($string)
    {
        $string = $this->convertEncryptedLink($string);

        return $string;
    }

    public function convertEncryptedLink($body)
    {
        $body = $this->convertMarkdownEncryptedLink($body);
        $body = $this->convertTwigEncryptedLink($body);

        return $body;
    }

    public function convertMarkdownEncryptedLink(string $body)
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

    // NEXT MAJOR : Move it in a dedicated shortcode TwigEncryptedLink
    public function convertTwigEncryptedLink($body)
    {
        $rgx = '/{{\s*j?s?link\(((?<![\\\])[\'"])((?:.(?!(?<![\\\])\1))*.?)\1\s*,'
                .'\s*((?<![\\\])[\'"])((?:.(?!(?<![\\\])\3))*.?)\3,?.*\)\s*}}/iU';
        preg_match_all($rgx, $body, $matches);

        if (! isset($matches[2])) {
            return $body;
        }

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = str_replace($matches[0][$k],  $this->renderEncryptedLink($matches[2][$k], $matches[4][$k]), $body);
        }

        return $body;
    }
}
