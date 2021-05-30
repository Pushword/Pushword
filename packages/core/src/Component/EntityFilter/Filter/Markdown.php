<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\MarkdownParser;

class Markdown extends AbstractFilter
{
    /**
     * @return string
     */
    public function apply($propertyValue)
    {
        $propertyValue = $this->render($propertyValue);

        return $propertyValue;
    }

    private function render(string $string): string
    {
        return (new MarkdownParser())->transform($string);
    }
}
