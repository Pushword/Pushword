<?php

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Opens a notice on `> [!label]`, optionally followed by a title:
 *
 *     > [!warning] Version
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

        return BlockStart::of(new NoticeParser(strtolower($matches[1]), $matches[2] ?? ''))->at($cursor);
    }
}
