<?php

namespace Pushword\Search\Service;

use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;

use function Safe\preg_replace;

/**
 * Produces the plain-text `content` indexed for a page.
 *
 * The page body is rendered exactly as `raw.twig` does — through the
 * EntityFilter pipeline (`pw(page).mainContent`) — so Markdown, Twig and
 * shortcodes (`pages_list`, `pages()`, …) execute and the indexed text matches
 * what visitors read. The rendered HTML is then stripped to normalized plain
 * text: Loupe is fed text, never HTML (which would bloat the index and
 * tokenize tags/URLs into noise).
 */
final readonly class TextExtractor
{
    public function __construct(
        private ManagerPool $managerPool,
    ) {
    }

    public function extract(Page $page): string
    {
        $html = $this->managerPool->getManager($page)->getMainContent();

        return self::toPlainText($html);
    }

    public static function toPlainText(string $html): string
    {
        // Strip <script>/<style> element *contents* so an embedded ld+json or
        // CSS block can't leak raw text into the index.
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
