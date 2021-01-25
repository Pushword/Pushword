<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Twig\EmailTwigTrait;

class Email extends AbstractFilter
{
    use EmailTwigTrait;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    /**
     * @return string
     */
    public function apply($string)
    {
        $string = $this->convertEmail($string);

        return $string;
    }

    public function convertEmail(string $body): string
    {
        $rgx = '/ ([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}) /i';

        preg_match_all($rgx, $body, $matches);

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = ' '.str_replace($matches[0][$k], $this->renderEncodedMail($this->twig, $matches[1][$k]), $body).' ';
        }

        return $body;
    }
}
