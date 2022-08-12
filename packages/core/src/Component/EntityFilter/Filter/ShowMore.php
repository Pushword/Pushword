<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;

class ShowMore extends AbstractFilter
{
    use RequiredAppTrait;
    use RequiredTwigTrait;

    public const TO_ADD_BEFORE = '<div class="show-more">
    <input type="checkbox" id="%id%" class="hidden show-hide-input">
    <div class="max-h-[120px] overflow-hidden transition-all delay-75 duration-300">';

    public const TO_ADD_AFTER = '<div class="text-center -mt-6">
      <label for="%id%" class="text-3xl text-gray-600 cursor-pointer after:content-[\'↥\']"></label>
    </div>
    </div>
    <div class="show-more-btn transition-all delay-75 duration-150 text-center pt-20 -mt-[120px] h-[120px] bg-gradient-to-b from-transparent to-white relative z-10">
      <label for="%id%" class="text-6xl text-gray-600 cursor-pointer after:content-[\'↧\']"></label>
    </div></div>';

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
