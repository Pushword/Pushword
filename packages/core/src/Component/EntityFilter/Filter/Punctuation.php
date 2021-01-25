<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\HtmlBeautifer;

class Punctuation extends AbstractFilter
{
    /**
     * @return string
     */
    public function apply($string)
    {
        $string = HtmlBeautifer::punctuationBeautifer($string);

        return $string;
    }
}
