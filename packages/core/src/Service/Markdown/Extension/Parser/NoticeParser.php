<?php

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;
use Override;
use Pushword\Core\Service\Markdown\Extension\Node\Notice;

/**
 * Continues a notice exactly like a blockquote — same `>` prefix, same lazy
 * continuation — only the node type and the marker line differ.
 */
final class NoticeParser extends AbstractBlockContinueParser
{
    private readonly Notice $block;

    public function __construct(string $level, string $title)
    {
        $this->block = new Notice($level, $title);
    }

    public function getBlock(): Notice
    {
        return $this->block;
    }

    #[Override]
    public function isContainer(): bool
    {
        return true;
    }

    #[Override]
    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($cursor->isIndented() || '>' !== $cursor->getNextNonSpaceCharacter()) {
            return BlockContinue::none();
        }

        $cursor->advanceToNextNonSpaceOrTab();
        $cursor->advanceBy(1);
        $cursor->advanceBySpaceOrTab();

        return BlockContinue::at($cursor);
    }
}
