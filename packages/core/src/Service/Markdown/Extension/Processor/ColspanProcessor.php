<?php

namespace Pushword\Core\Service\Markdown\Extension\Processor;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;

final class ColspanProcessor
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if (! $node instanceof TableRow) {
                continue;
            }

            $this->processRow($node);
        }
    }

    private function processRow(TableRow $row): void
    {
        $previousCell = null;
        $colspan = 1;

        foreach ($row->children() as $cell) {
            if (! $cell instanceof TableCell) {
                continue;
            }

            if ($this->isColspanMarker($cell)) {
                if (null !== $previousCell) {
                    ++$colspan;
                    $cell->detach();
                }

                continue;
            }

            $this->applyColspan($previousCell, $colspan);

            $previousCell = $cell;
            $colspan = 1;
        }

        $this->applyColspan($previousCell, $colspan);
    }

    private function applyColspan(?TableCell $cell, int $colspan): void
    {
        if (null !== $cell && $colspan > 1) {
            $cell->data->set('attributes/colspan', (string) $colspan);
        }
    }

    private function isColspanMarker(TableCell $cell): bool
    {
        /** @var list<Node> $children */
        $children = [...$cell->children()];
        if (1 !== count($children)) {
            return false;
        }

        return $children[0] instanceof Text && '->' === trim($children[0]->getLiteral());
    }
}
