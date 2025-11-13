<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Utils\MarkdownUtils;

use function Safe\preg_replace;

use Twig\Environment;

/**
 * Name is misleading, it's not a markdown filter, it's a twig-markdown filter.
 */
class Markdown extends AbstractFilter
{
    public ?Manager $manager = null;

    public Environment $twig;

    public MarkdownParser $markdownParser;

    public function apply(mixed $propertyValue): string
    {
        return $this->render($this->string($propertyValue));
    }

    private function render(string $text): string
    {
        $textPartList = MarkdownUtils::prepareText($text);

        // must take care of code block

        $filteredText = '';
        foreach ($textPartList as $textPart) {
            $filteredText .= $this->transformPart($textPart)."\n\n";
        }

        return $filteredText;
    }

    private function parseMarkdown(string $text): string
    {
        // return $this->twig->createTemplate('{% apply markdown %}'.$text.'{% endapply %}')->render();

        return $this->markdownParser->transform($text);
    }

    private function transformPart(string $text): string
    {
        // dump($text);
        $lines = explode("\n", $text);
        $attribute = '';
        if (MarkdownUtils::startWithAttribute($lines[0])) {
            $attribute = $lines[0];
            $attribute = str_replace('{#', '{id=', $attribute);
            unset($lines[0]);
        }

        $blockText = implode("\n", $lines);

        $textFiltered = null;
        if (! MarkdownUtils::isItCodeBlock($blockText)) {
            $inlineCodeProtector = new MarkdownProtectInlineCode();
            $textFiltered = $inlineCodeProtector->protect($blockText);
            $textFiltered = $this->manager?->applyFilters($textFiltered, ['twig']);
            assert(is_string($textFiltered));
            $textFiltered = $inlineCodeProtector->restore($textFiltered);
        }

        if (null !== $textFiltered) {
            if (MarkdownUtils::isItRawBlock($blockText)) {
                return $textFiltered;
            }

            $textFiltered = trim($textFiltered);
            /** @var string $blockText */
            // $blockText = preg_replace('/^ +/m', '', $textFiltered);
            $blockText = $textFiltered;
        }

        $blockText = $this->fixTypo($blockText);

        $markdown = $this->parseMarkdown(trim($attribute."\n".$blockText));

        return $markdown;
    }

    private function fixTypo(string $text): string
    {
        // only when typing #, we are adding an insecable space wich breaks the markdown parser.
        $text = preg_replace('/^^(#{1,6})[\x{A0}]+/u', '$1 ', $text);
        assert(is_string($text));

        // next lineto remove when https://github.com/thephpleague/commonmark/pull/1096 is merged
        $text = str_replace(' viewBox="', ' viewbox="', $text);

        return $text;
    }
}
