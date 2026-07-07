<?php

namespace Pushword\Core\Service\Markdown\Extension\Processor;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\NodeIterator;

/**
 * Drop a table's header section when every header cell is empty.
 *
 * The block editor stores a headerless table (label/value grids) as a GFM pipe
 * table with an empty header row, because CommonMark only recognises a table
 * when a delimiter — and therefore a header row — is present. Rendering that
 * empty <thead> would show a blank bar above the data, so we remove it here and
 * let the table render with its <tbody> only, matching the original HTML table.
 */
final class EmptyTableHeadProcessor
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if ($node instanceof TableSection && $node->isHead() && $this->isEmpty($node)) {
                $node->detach();
            }
        }
    }

    private function isEmpty(TableSection $head): bool
    {
        $hasCell = false;

        foreach ($head->children() as $row) {
            if (! $row instanceof TableRow) {
                continue;
            }

            foreach ($row->children() as $cell) {
                if (! $cell instanceof TableCell) {
                    continue;
                }

                $hasCell = true;
                if (! $this->isEmptyCell($cell)) {
                    return false;
                }
            }
        }

        return $hasCell;
    }

    private function isEmptyCell(TableCell $cell): bool
    {
        foreach ($cell->children() as $child) {
            if (! $child instanceof Text || '' !== trim($child->getLiteral())) {
                return false;
            }
        }

        return true;
    }
}
