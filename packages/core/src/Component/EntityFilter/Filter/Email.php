<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use Pushword\Core\Twig\LinkTwigTrait;

class Email extends AbstractFilter
{
    use LinkTwigTrait;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    public function apply($propertyValue): string
    {
        return $this->convertEmail(\strval($propertyValue));
    }

    public function convertEmail(string $body): string
    {
        $rgx = '/ ([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4})( |\.<\/|\. |$)/i';

        \Safe\preg_match_all($rgx, $body, $matches);

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = str_replace(
                $matches[0][$k],
                ' '.$this->renderEncodedMail($matches[1][$k]).$matches[2][$k],
                $body
            );
        }

        return $body;
    }
}
