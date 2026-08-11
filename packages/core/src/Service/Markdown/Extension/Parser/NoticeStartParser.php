<?php

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use League\CommonMark\Extension\Attributes\Util\AttributesHelper;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Opens a notice on `> [!label]`, optionally followed by a title:
 *
 *     > [!warning] Version {#version}
 *     >
 *     > Last updated: August 2026.
 *
 * The label is free-form and case-insensitive; the marker line is consumed
 * whole, so only what follows it becomes the notice body. A blockquote whose
 * first line does not match keeps CommonMark's own parser (priority 70).
 */
final class NoticeStartParser implements BlockStartParserInterface
{
    /**
     * The label charset excludes `[`, so a body opening on a linked image —
     * `> [![alt](img.jpg)](/page)` — cannot be read as a marker.
     */
    private const string MARKER = '/^\[!([a-z][a-z0-9_-]*)\](?:[ \t]+(\S.*?))?[ \t]*$/i';

    /**
     * `{#anchor .class}` closing the marker line, as on a heading — the form an
     * editor writes when the notice carries an anchor. A brace ending on `#` is a
     * Twig comment, and anything the attribute syntax cannot read stays in the title.
     */
    private const string TRAILING_ATTRIBUTES = '/[ \t]*(\{[^}\n]*(?<!#)\})$/';

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || '>' !== $cursor->getNextNonSpaceCharacter()) {
            return BlockStart::none();
        }

        $state = $cursor->saveState();

        $cursor->advanceToNextNonSpaceOrTab();
        $cursor->advanceBy(1);
        $cursor->advanceBySpaceOrTab();

        if (1 !== preg_match(self::MARKER, $cursor->getRemainder(), $matches)) {
            $cursor->restoreState($state);

            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        [$title, $attributes] = $this->splitTitle($matches[2] ?? '');

        return BlockStart::of(new NoticeParser(strtolower($matches[1]), $title, $attributes))->at($cursor);
    }

    /**
     * @return array{string, array<string, mixed>} the title, then the attributes
     *                                             it was carrying
     */
    private function splitTitle(string $title): array
    {
        if (1 !== preg_match(self::TRAILING_ATTRIBUTES, $title, $trailing, \PREG_OFFSET_CAPTURE)) {
            return [$title, []];
        }

        // mergeAttributes() joins the class list parseAttributes() returns as an
        // array, which is what an `{#anchor}` line above the notice also yields.
        $attributes = AttributesHelper::mergeAttributes([], AttributesHelper::parseAttributes(new Cursor($trailing[1][0])));

        return [] === $attributes
            ? [$title, []]
            : [substr($title, 0, $trailing[0][1]), $attributes];
    }
}
