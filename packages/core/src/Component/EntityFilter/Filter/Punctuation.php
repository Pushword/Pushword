<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\HtmlBeautifer;

class Punctuation extends AbstractFilter
{
    public function apply($propertyValue): string
    {
        return HtmlBeautifer::punctuationBeautifer(\strval($propertyValue));
    }
}
