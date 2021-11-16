<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use Pushword\Core\Twig\PhoneNumberTwigTrait;

class PhoneNumber extends AbstractFilter
{
    use PhoneNumberTwigTrait;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    public function apply($propertyValue): string
    {
        return $this->convertPhoneNumber(\strval($propertyValue));
    }

    private function convertPhoneNumber(string $body): string
    {
        $rgx = '/ (?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4} /iU';
        \Safe\preg_match_all($rgx, $body, $matches);

        if (! isset($matches[0])) {
            return $body;
        }

        foreach ($matches[0] as $m) {
            $body = str_replace($m, $this->renderPhoneNumber($m), $body);
        }

        return $body;
    }
}
