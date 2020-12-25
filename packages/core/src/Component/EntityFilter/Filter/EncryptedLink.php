<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Service\LinkProvider;

use function Safe\preg_match_all;

use Twig\Environment;

class EncryptedLink extends AbstractFilter
{
    public LinkProvider $linkProvider;

    public AppConfig $app;

    public Environment $twig;

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
        preg_match_all('/(?:#\[(.*?)\]\((.*?)\))({(?:([#.][-_:a-zA-Z0-9 ]+)+)\})?/', $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        /** @var array<int, array<int, string>> $matches */
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
            $link = $this->linkProvider->renderLink($matches[$anchorKey][$k], $matches[$hrefKey][$k], $attr);
            $body = str_replace($matches[0][$k], $link, $body);
        }

        return $body;
    }
}
