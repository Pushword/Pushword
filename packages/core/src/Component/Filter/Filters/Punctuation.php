<?php

namespace Pushword\Core\Component\Filter\Filters;

use Pushword\Core\Utils\HtmlBeautifer;

class Punctuation extends ShortCode
{
    public function apply($string)
    {
        $string = HtmlBeautifer::punctuationBeautifer($string);

        return $string;
    }
}
