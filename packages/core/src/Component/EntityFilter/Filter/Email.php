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

    /**
     * @return string
     */
    public function apply($propertyValue)
    {
        $propertyValue = $this->convertEmail($propertyValue);

        return $propertyValue;
    }

    public function convertEmail(string $body): string
    {
        $rgx = '/ ([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}) /i';

        preg_match_all($rgx, $body, $matches);

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = ' '.str_replace($matches[0][$k], $this->renderEncodedMail($matches[1][$k]), $body).' ';
        }

        return $body;
    }
}
