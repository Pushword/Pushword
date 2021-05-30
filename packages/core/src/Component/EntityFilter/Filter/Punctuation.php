<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\HtmlBeautifer;

class Punctuation extends AbstractFilter
{
    /**
     * @return string
     */
    public function apply($propertyValue)
    {
        $propertyValue = HtmlBeautifer::punctuationBeautifer($propertyValue);

        return $propertyValue;
    }
}
