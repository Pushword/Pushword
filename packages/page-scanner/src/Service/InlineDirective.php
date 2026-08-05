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
        preg_match_all('/<!--\s*'.preg_quote($name, '/').':(.*?)-->/is', self::stripCodeSamples($content), $matches);

        $patterns = [];
        foreach ($matches[1] as $declaration) {
            // A comma separates two patterns unless it is escaped: URLs carry commas
            // (map coordinates), and a pattern has to be able to name one. Parking the
            // escaped ones on a byte no content holds keeps the split a plain explode.
            foreach (explode(',', str_replace('\,', "\0", $declaration)) as $declared) {
                if ('' !== ($pattern = str_replace("\0", ',', trim($declared)))) {
                    $patterns[] = $pattern;
                }
            }
        }

        return $patterns;
    }

    /**
     * A directive written in a code sample documents the syntax, it does not ask for
     * anything. Without this the scanner's own documentation page silences every
     * finding it illustrates, and so does any page quoting it.
     */
    private static function stripCodeSamples(string $content): string
    {
        // Fenced blocks first, so their content cannot open an inline span. A fence
        // left unclosed runs to the end of the content, as CommonMark reads it.
        $content = preg_replace('/^ {0,3}(`{3,}|~{3,}).*?(?:^ {0,3}\1[ \t]*$|\z)/ms', '', $content) ?? $content;

        return preg_replace('/(`+)(?:(?!\1).)*\1/s', '', $content) ?? $content;
    }
}
