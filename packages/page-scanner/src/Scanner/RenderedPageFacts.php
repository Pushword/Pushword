<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Scanner;

/** Facts extracted once from rendered HTML for the page scanners. */
final readonly class RenderedPageFacts
{
    /**
     * @param list<string> $hrefs
     * @param list<string> $missingAlt
     * @param list<string> $anchors
     * @param list<string> $linkedDocs
     * @param list<string> $crawlableLinks
     * @param list<string> $mailtoLinks
     * @param list<string> $dateShortcodes
     */
    public function __construct(
        public array $hrefs,
        public array $missingAlt,
        public array $anchors,
        public array $linkedDocs,
        public array $crawlableLinks,
        public array $mailtoLinks,
        public array $dateShortcodes,
    ) {
    }
}
