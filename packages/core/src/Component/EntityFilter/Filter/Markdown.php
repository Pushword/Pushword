<?php

declare(strict_types=1);

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
        // Convert inline heading attributes `## Title {#id}` to block syntax `{#id}\n## Title`
        $text = preg_replace('/^(#{1,6}\s.+?)[\t ]*(\{[#.][^}]+(?<!#)\})[\t ]*\r?$/m', "$2\n$1", $text);
        assert(is_string($text));

        $textPartList = MarkdownUtils::prepareText($text);

        if ($this->markdownParser->hasNativeMarkdown()) {
            return $this->renderNativeBatch(array_values($textPartList), $manager);
        }

        // must take care of code block

        $filteredText = '';
        foreach ($textPartList as $index => $textPart) {
            try {
                $filteredText .= $this->transformPart($textPart, $manager)."\n\n";
            } catch (Exception $e) {
                throw $this->blockError($index, $textPart, $e);
            }
        }

        return $filteredText;
    }

    /** @param list<string> $parts */
    private function renderNativeBatch(array $parts, Manager $manager): string
    {
        $prepared = [];
        $markdown = [];
        foreach ($parts as $index => $part) {
            try {
                [$content, $needsMarkdown] = $this->preparePart($part, $manager);
            } catch (Exception $e) {
                throw $this->blockError($index, $part, $e);
            }

            $prepared[] = [$content, $needsMarkdown];
            if ($needsMarkdown) {
                $markdown[] = $content;
            }
        }

        $native = $this->markdownParser->renderNativeMany($markdown);
        $markdownIndex = 0;
        $filteredText = '';
        foreach ($prepared as $index => [$content, $needsMarkdown]) {
            if ($needsMarkdown) {
                try {
                    $content = $native[$markdownIndex] ?? $this->parseMarkdown($content);
                } catch (Exception $e) {
                    throw $this->blockError($index, $parts[$index], $e);
                }

                ++$markdownIndex;
            }

            $filteredText .= $content."\n\n";
        }

        return $filteredText;
    }

    private function blockError(int $index, string $part, Exception $error): Exception
    {
        return new Exception(\sprintf('Error in markdown block #%d: "%s" — %s', $index + 1, mb_substr(trim($part), 0, 100), $error->getMessage()), 0, $error);
    }

    private function parseMarkdown(string $text): string
    {
        return $this->markdownParser->transform($text);
    }

    private function transformPart(string $text, Manager $manager): string
    {
        [$content, $needsMarkdown] = $this->preparePart($text, $manager);

        return $needsMarkdown ? $this->parseMarkdown($content) : $content;
    }

    /** @return array{string, bool} */
    private function preparePart(string $text, Manager $manager): array
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
            $codeBlockProtector = new MarkdownProtectCodeBlock();
            $inlineCodeProtector = new MarkdownProtectInlineCode();
            $textFiltered = $codeBlockProtector->protect($blockText);
            $textFiltered = $inlineCodeProtector->protect($textFiltered);
            $textFiltered = preg_replace('/\{#([a-zA-Z0-9_-]+)\}/', '{id=$1}', $textFiltered);
            assert(\is_string($textFiltered));
            $textFiltered = $manager->applyFilters($textFiltered, ['twig']);
            assert(is_string($textFiltered));
            $textFiltered = $inlineCodeProtector->restore($textFiltered);
            $textFiltered = $codeBlockProtector->restoreString($textFiltered);
        }

        if (null !== $textFiltered) {
            if (MarkdownUtils::isItRawBlock($blockText)) {
                return [$textFiltered, false];
            }

            $textFiltered = trim($textFiltered);
            // $blockText = preg_replace('/^ +/m', '', $textFiltered);
            $blockText = $textFiltered;
        }

        $blockText = $this->fixTypo($blockText);

        return [trim($attribute."\n".$blockText), true];
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
