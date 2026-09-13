<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Scanner;

/** Facts extracted once from rendered HTML for the page scanners. */
final readonly class RenderedPageFacts
{
    /**
     * @param list<string>                             $hrefs
     * @param list<string>                             $missingAlt
     * @param list<string>                             $anchors
     * @param list<array{name: string, value: string}> $linkedAttributes
     * @param list<string>                             $srcsets
     * @param list<string>                             $dateShortcodes
     */
    public function __construct(
        public array $hrefs,
        public array $missingAlt,
        public array $anchors,
        public array $linkedAttributes,
        public array $srcsets,
        public array $dateShortcodes,
    ) {
    }
}
