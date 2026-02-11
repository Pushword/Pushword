<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Exception;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Utils\MarkdownUtils;

use function Safe\preg_replace;

/**
 * Name is misleading, it's not a markdown filter, it's a twig-markdown filter.
 */
#[AsFilter]
class Markdown implements FilterInterface
{
    public function __construct(
        private readonly MarkdownParser $markdownParser,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));

        return $this->render((string) $propertyValue, $manager);
    }

    private function render(string $text, Manager $manager): string
    {
        $textPartList = MarkdownUtils::prepareText($text);

        // must take care of code block

        $filteredText = '';
        foreach ($textPartList as $index => $textPart) {
            try {
                $filteredText .= $this->transformPart($textPart, $manager)."\n\n";
            } catch (Exception $e) {
                throw new Exception(\sprintf('Error in markdown block #%d: "%s" — %s', $index + 1, mb_substr(trim($textPart), 0, 100), $e->getMessage()), 0, $e);
            }
        }

        return $filteredText;
    }

    private function parseMarkdown(string $text): string
    {
        return $this->markdownParser->transform($text);
    }

    private function transformPart(string $text, Manager $manager): string
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
            $textFiltered = $manager->applyFilters($textFiltered, ['twig']);
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
