<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use Pushword\Core\Twig\LinkTwigTrait;

use function Safe\preg_match_all;

class Email extends AbstractFilter
{
    use LinkTwigTrait;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    public function apply(mixed $propertyValue): string
    {
        return $this->convertEmail($this->string($propertyValue));
    }

    /**
     * @psalm-suppress PossiblyUndefinedMethod
     */
    public function convertEmail(string $body): string
    {
        $rgx = '/ ([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4})( |\.<\/|<\/p|\. |$)/i';

        preg_match_all($rgx, $body, $matches);

        $nbrMatch = is_countable($matches[0]) ? \count($matches[0]) : 0;
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = str_replace(
                $matches[0][$k],
                ' '.trim($this->renderEncodedMail($matches[1][$k])).$matches[2][$k],
                $body
            );
        }

        return $body;
    }
}
