<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Utils\MarkdownParser;

class Markdown extends AbstractFilter
{
    /**
     * @return string
     */
    public function apply($string)
    {
        $string = $this->render($string);

        return $string;
    }

    private function render(string $string): string
    {
        return (new MarkdownParser())->transform($string);
    }
}
