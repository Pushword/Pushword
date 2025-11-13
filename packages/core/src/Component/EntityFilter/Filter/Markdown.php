<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Service\Markdown\MarkdownParser;

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

    private function normalizeNewLine(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        // small fix for prettier
        $text = str_replace("}\n\n<", "}\n<", $text);

        return $text;
    }

    private function render(string $text): string
    {
        $text = $this->normalizeNewLine($text);
        $codeBlockProtector = new MarkdownProtectCodeBlock();
        $text = $codeBlockProtector->protect($text);
        $textPartList = explode("\n\n", $text);
        $textPartList = $codeBlockProtector->restore($textPartList);

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

    public function transformPart(string $text): string
    {
        // dump($text);
        $lines = explode("\n", $text);
        $attribute = '';
        if ($this->startWithAttribute($lines[0])) {
            $attribute = $lines[0];
            $attribute = str_replace('{#', '{id=', $attribute);
            unset($lines[0]);
        }

        $blockText = implode("\n", $lines);

        $textFiltered = null;
        if (! $this->isItCodeBlock($blockText)) {
            $inlineCodeProtector = new MarkdownProtectInlineCode();
            $textFiltered = $inlineCodeProtector->protect($blockText);
            $textFiltered = $this->manager?->applyFilters($textFiltered, ['twig']);
            assert(is_string($textFiltered));
            $textFiltered = $inlineCodeProtector->restore($textFiltered);
        }

        if (null !== $textFiltered) {
            if ($this->isItRawBlock($blockText)) {
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

    private function isItCodeBlock(string $text): bool
    {
        return str_starts_with($text, '```') && str_ends_with($text, '```');
    }

    private function isItRawBlock(string $text): bool
    {
        $text = trim($text);

        return str_starts_with($text, '{') || str_starts_with($text, '<');
    }

    private function startWithAttribute(string $firstLine): bool
    {
        $line = trim($firstLine);

        if (str_starts_with($line, '{#') && (str_ends_with($line, '#}') || ! str_ends_with($line, '}'))) {
            return false; // it's a twig comment
        }

        return
            str_starts_with($line, '{')
            && str_ends_with($line, '}')
            && ! str_starts_with($line, '{{')
            && ! str_starts_with($line, '{%')
        ;
    }
}
