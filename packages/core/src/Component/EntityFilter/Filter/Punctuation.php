<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\HtmlBeautifer;

class Punctuation extends AbstractFilter
{
    public function apply(mixed $propertyValue): string
    {
        $propertyValue = $this->string($propertyValue);
        $propertyValue = str_replace(' .', '.', $propertyValue);
        $propertyValue = str_replace(' ,', ',', $propertyValue);

        return HtmlBeautifer::punctuationBeautifer($propertyValue);
    }
}
