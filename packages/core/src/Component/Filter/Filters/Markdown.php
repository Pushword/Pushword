<?php

namespace Pushword\Core\Component\Filter\Filters;

use Knp\Bundle\MarkdownBundle\MarkdownParserInterface;

class Markdown extends ShortCode
{
    /** @var MarkdownParserInterface */
    protected $markdownParser;

    public function apply($string)
    {
        $string = $this->render($string);

        return $string;
    }

    public function setMarkdownParser(MarkdownParserInterface $markdownParser)
    {
        $this->markdownParser = $markdownParser;
    }

    protected function render($string)
    {
        return $this->markdownParser->transformMarkdown($string);
    }
}
