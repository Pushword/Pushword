<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Utils\MarkdownParser;

use function Safe\preg_replace;

class Markdown extends AbstractFilter
{
    private readonly MarkdownParser $markdownParser;

    public ?Manager $manager = null;

    public function __construct()
    {
        $this->markdownParser = new MarkdownParser();
    }

    public function apply(mixed $propertyValue): string
    {
        return $this->render($this->string($propertyValue));
    }

    private function normalizeNewLine(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        return $text;
    }

    private function render(string $text): string
    {
        $text = $this->normalizeNewLine($text);
        $textPartList = explode("\n\n", $text);

        $filteredText = '';
        foreach ($textPartList as $textPart) {
            $filteredText .= $this->transformPart($textPart)."\n\n";
        }

        return $filteredText;
    }

    public function transformPart(string $text): string
    {
        // dump($text);
        $lines = explode("\n", $text);
        $attribute = '';
        if ($this->startWithAttribute($lines[0])) {
            $attribute = $lines[0];
            unset($lines[0]);
        }

        $blockText = implode("\n", $lines);

        $textFiltered = null;
        if (! $this->isItCodeBlock($blockText)) {
            $textFiltered = $this->manager?->applyFilters($blockText, explode(',', 'twig,date,email,htmlLinkMultisite,obfuscateLink,htmlObfuscateLink,image,phoneNumber'));
        }

        if (null !== $textFiltered && is_string($textFiltered)) {
            if (str_starts_with($blockText, '{{')) {
                return $textFiltered;
            }

            $textFiltered = trim($textFiltered);
            /** @var string $blockText */
            $blockText = preg_replace('/^ +/m', '', $textFiltered);
        }

        return $this->markdownParser->transform(trim($attribute."\n".$blockText));
    }

    private function isItCodeBlock(string $text): bool
    {
        return str_starts_with($text, '```') && str_ends_with($text, '```');
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
