<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\MarkdownParser;

class Markdown extends AbstractFilter
{
    public function apply(mixed $propertyValue): string
    {
        return $this->render($this->string($propertyValue));
    }

    private function render(string $string): string
    {
        return (new MarkdownParser())->transform($string);
    }
}
