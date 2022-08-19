<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;

class ShowMore extends AbstractFilter
{
    use RequiredAppTrait;
    use RequiredTwigTrait;

    public const TO_ADD_BEFORE = '{{ block("before", view("/component/show_more.html.twig")) %}';

    public const TO_ADD_AFTER = '{{ block("after", view("/component/show_more.html.twig")) %}';

    public function apply($propertyValue): string
    {
        return $this->showMore(\strval($propertyValue));
    }

    private function showMore(string $body): string
    {
        $bodyParts = explode('<!--start-show-more-->', $body);
        $body = '';
        foreach ($bodyParts as $bodyPart) {
            if (! str_contains($bodyPart, '<!--end-show-more-->')) {
                $body .= $bodyPart;

                continue;
            }

            $id = 'sh-'.substr(md5('sh'.$bodyPart), 0, 4);
            $body .= str_replace('%id%', $id, self::TO_ADD_BEFORE)
                .str_replace('<!--end-show-more-->', str_replace('%id%', $id, self::TO_ADD_AFTER), $bodyPart);
        }

        return $body;
    }
}
