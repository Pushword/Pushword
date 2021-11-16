<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\MarkdownParser;

class Markdown extends AbstractFilter
{
    public function apply($propertyValue): string
    {
        return $this->render(\strval($propertyValue));
    }

    private function render(string $string): string
    {
        return (new MarkdownParser())->transform($string);
    }
}
