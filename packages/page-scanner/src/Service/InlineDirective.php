<?php

namespace Pushword\PageScanner\Service;

/**
 * An instruction an editor leaves for the scanner inside the content itself.
 *
 * `<!-- name: a, b -->` — one comment can list several patterns, and a page can carry
 * several comments. Read from the content rather than from the rendered HTML: a page
 * whose render failed produces no HTML, and that is precisely when an editor may need
 * to say something about it.
 */
final class InlineDirective
{
    /**
     * @return string[]
     */
    public static function patterns(string $content, string $name): array
    {
        // The colon is part of the name being matched, so `page-scanner-ignore` does
        // not swallow a `page-scanner-ignore-link` comment.
        preg_match_all('/<!--\s*'.preg_quote($name, '/').':(.*?)-->/is', $content, $matches);

        $patterns = [];
        foreach ($matches[1] as $declaration) {
            foreach (explode(',', $declaration) as $declared) {
                if ('' !== ($pattern = trim($declared))) {
                    $patterns[] = $pattern;
                }
            }
        }

        return $patterns;
    }
}
