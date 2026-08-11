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

    /**
     * @param array<string, mixed> $attributes written at the end of the marker line
     */
    public function __construct(string $level, string $title, array $attributes = [])
    {
        $this->block = new Notice($level, $title);

        // AttributesListener merges an `{#anchor}` line above the notice into
        // whatever sits here, so both forms can be used on the same notice.
        $this->block->data->set('attributes', $attributes);
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
