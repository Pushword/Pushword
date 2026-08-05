<?php

namespace Pushword\Core\Service\Markdown\Extension\Node;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A blockquote opened by a `> [!label]` marker — the DocFX / GitHub alert
 * syntax, with a free label and an optional title on the marker line.
 */
final class Notice extends AbstractBlock
{
    public function __construct(
        /** Lowercased label, safe to use as a CSS class suffix. */
        public readonly string $level,
        /** Empty when the marker line carried no title. */
        public readonly string $title,
    ) {
        parent::__construct();
    }
}
