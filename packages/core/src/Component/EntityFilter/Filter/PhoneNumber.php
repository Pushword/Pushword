<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use Pushword\Core\Twig\PhoneNumberTwigTrait;

use function Safe\preg_match_all;

class PhoneNumber extends AbstractFilter
{
    use PhoneNumberTwigTrait;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    public function apply(mixed $propertyValue): string
    {
        return $this->convertPhoneNumber($this->string($propertyValue));
    }

    private function convertPhoneNumber(string $body): string
    {
        $rgx = '(?:[^0-9] |>)(?:(?:\+|00)33|0)(\s|\xC2\xA0|&nbsp;)*[1-9](?:([\s.-\xC2\xA0]|&nbsp;)*\d{2}){4}(?P<after>( |&nbsp;)|\.<\/|\. |$)';
        preg_match_all($rgx, $body, $matches);

        if (! isset($matches[0])) {
            return $body;
        }

        foreach ($matches[0] as $k => $m) {
            $after = $matches['after'][$k];
            $body = str_replace($m, ' '.$this->renderPhoneNumber(trim(substr((string) $m, 0, -\strlen((string) $after)))).$after, $body);
        }

        return $body;
    }
}
