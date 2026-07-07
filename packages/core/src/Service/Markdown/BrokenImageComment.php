<?php

namespace Pushword\Core\Service\Markdown;

/**
 * Marker emitted in place of a body image (`![](…)`) that could not be resolved.
 *
 * A single missing media must degrade to an invisible comment, never take down
 * the whole page with a 500. The source is html-escaped so a value containing
 * `-->` cannot break out of the comment. Both the page scanner and the admin
 * edit warning detect pages carrying this marker.
 */
final class BrokenImageComment
{
    /** Matches the emitted marker and captures the (escaped) offending source. */
    public const string PATTERN = '/<!-- pushword:broken-image src="([^"]*)" -->/';

    public static function for(string $src): string
    {
        return '<!-- pushword:broken-image src="'.htmlspecialchars($src, \ENT_QUOTES).'" -->';
    }

    /**
     * Sources of every broken-image marker present in $html, decoded back to
     * their original form.
     *
     * @return string[]
     */
    public static function extractSources(string $html): array
    {
        if (false === preg_match_all(self::PATTERN, $html, $matches) || [] === $matches[1]) {
            return [];
        }

        return array_map(htmlspecialchars_decode(...), $matches[1]);
    }
}
