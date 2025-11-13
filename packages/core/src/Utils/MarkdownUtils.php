<?php

namespace Pushword\Core\Utils;

use Pushword\Core\Component\EntityFilter\Filter\MarkdownProtectCodeBlock;
use Pushword\Core\Entity\Page;

final class MarkdownUtils
{
    public static function normalizeNewLine(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        // small fix for prettier
        $text = str_replace("}\n\n<", "}\n<", $text);

        return $text;
    }

    public static function startWithAttribute(string $firstLine): bool
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

    public static function isItCodeBlock(string $text): bool
    {
        return str_starts_with($text, '```') && str_ends_with($text, '```');
    }

    public static function isItRawBlock(string $text): bool
    {
        $text = trim($text);

        return str_starts_with($text, '{') || str_starts_with($text, '<');
    }

    public static function detectMarkdownBlockType(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^#{1,6}\s/', $text) > 0) {
            return 'header';
        }

        if (preg_match('/^[-*+]\s/', $text) > 0 || preg_match('/^\d+\.\s/', $text) > 0) {
            return 'list';
        }

        if (str_starts_with($text, '>')) {
            return 'quote';
        }

        if (self::isItCodeBlock($text)) {
            return 'code';
        }

        if (str_starts_with($text, '|')) {
            return 'table';
        }

        if (self::isItRawBlock($text)) {
            return 'raw';
        }

        return 'paragraph';
    }

    /**
     * @return string[]
     */
    public static function prepareText(string $text): array
    {
        $text = self::normalizeNewLine($text);
        $codeBlockProtector = new MarkdownProtectCodeBlock();
        $text = $codeBlockProtector->protect($text);
        $textPartList = explode("\n\n", $text);
        $textPartList = $codeBlockProtector->restore($textPartList);

        return $textPartList;
    }

    /**
     * @param string[] $on types de blocs markdown à cibler (ex: ['header', 'paragraph'])
     */
    public static function addAnchor(Page $page, string $anchor, string $regex, array $on = ['header'], ?callable $outputCallback = null): void
    {
        $text = $page->getMainContent();
        $textPartList = self::prepareText($text);

        $modified = false;
        $modifiedText = '';

        foreach ($textPartList as $textPart) {
            if ($modified) {
                $modifiedText .= $textPart."\n\n";

                continue;
            }

            $lines = explode("\n", $textPart);
            $attribute = '';
            if (self::startWithAttribute($lines[0])) {
                $attribute = $lines[0];
                unset($lines[0]);
            }

            if (str_contains($attribute, '#')) {
                $modifiedText .= $textPart."\n\n";

                continue;
            }

            if (str_contains($attribute, '#'.$anchor.'}')) {
                break;
            }

            $blockText = implode("\n", $lines);
            $blockType = self::detectMarkdownBlockType($blockText);
            if (! \in_array($blockType, $on, true)) {
                $modifiedText .= $textPart."\n\n";

                continue;
            }

            // Vérifier si le bloc correspond à la regex
            if (preg_match($regex, $blockText) > 0) {
                // Ajouter l'ancre avant le bloc
                $attribute = '' === $attribute ? '{#'.$anchor.'}' : trim($attribute, '}').' #'.$anchor.'}';
                $modifiedText .= $attribute."\n".$blockText."\n\n";

                if (null !== $outputCallback) {
                    $outputCallback($page->getHost().'/'.$page->getSlug().' : '.$blockType.' updated with anchor `'.$anchor.'`');
                }

                $modified = true;

                continue;
            }

            $modifiedText .= $textPart."\n\n";
        }

        if ($modified) {
            $page->setMainContent($modifiedText);
        }
    }
}
