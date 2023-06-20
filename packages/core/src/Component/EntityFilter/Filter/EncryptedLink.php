<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use Pushword\Core\Twig\LinkTwigTrait;

class EncryptedLink extends AbstractFilter
{
    use LinkTwigTrait;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    public function apply(mixed $propertyValue): string
    {
        return $this->convertEncryptedLink($this->string($propertyValue));
    }

    public function convertEncryptedLink(string $body): string
    {
        return $this->convertMarkdownEncryptedLink($body);
    }

    public function convertMarkdownEncryptedLink(string $body): string
    {
        \Safe\preg_match_all('/(?:#\[(.*?)\]\((.*?)\))({(?:([#.][-_:a-zA-Z0-9 ]+)+)\})?/', $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        return $this->replaceEncryptedLink($body, $matches);
    }

    /**
     * @param array<int, array<int, string>> $matches
     */
    protected function replaceEncryptedLink(string $body, array $matches, int $hrefKey = 2, int $anchorKey = 1): string
    {
        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $attr = $matches[3][$k] ?? null;
            $attr = null !== $attr ? [('#' == $attr ? 'id' : 'class') => substr($attr, 1)] : [];
            $link = $this->renderLink($matches[$anchorKey][$k], $matches[$hrefKey][$k], $attr);
            $body = str_replace($matches[0][$k], $link, $body);
        }

        return $body;
    }
}
