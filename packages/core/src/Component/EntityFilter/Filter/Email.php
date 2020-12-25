<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Exception;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Service\LinkProvider;

use function Safe\preg_match_all;

use Twig\Environment;

class Email extends AbstractFilter
{
    public LinkProvider $linkProvider;

    public AppConfig $app;

    public Environment $twig;

    public function apply(mixed $propertyValue): string
    {
        return $this->convertEmail($this->string($propertyValue));
    }

    /**
     * @psalm-suppress all
     */
    public function convertEmail(string $body): string
    {
        $rgx = '/ ([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4})( |\.<\/|<\/p|\. |$)/i';

        preg_match_all($rgx, $body, $matches);

        if (null === $matches) {
            throw new Exception();
        }

        $nbrMatch = is_countable($matches[0]) ? \count($matches[0]) : 0;
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = str_replace(
                $matches[0][$k],
                ' '.trim($this->linkProvider->renderEncodedMail($matches[1][$k])).$matches[2][$k],
                $body
            );
        }

        return $body;
    }
}
