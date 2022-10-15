<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\HtmlBeautifer;

class Punctuation extends AbstractFilter
{
    public function apply($propertyValue): string
    {
        $propertyValue = \strval($propertyValue);
        $propetyValue = str_replace(' .', '.', $propertyValue);
        $propetyValue = str_replace(' ,', ',', $propertyValue);

        return HtmlBeautifer::punctuationBeautifer($propertyValue);
    }
}
