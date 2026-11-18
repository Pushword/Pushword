<?php

declare(strict_types=1);

namespace Pushword\Search\Service;

use function Safe\preg_replace;

/**
 * Splits French elisions (l’, d’, qu’, jusqu’…) off the word they precede.
 *
 * Loupe segments words with ICU (UAX #29), which joins letters across both the
 * straight and the typographic apostrophe: `l’acompte` is one term, so a search
 * for `acompte` misses it, and a search for `l’acompte` misses `un acompte`.
 * Loupe has no tokenizer hook, so the split happens on the text itself: indexed
 * values get an invisible word boundary after the particle, and queries drop
 * the particle.
 *
 * Temporary: https://github.com/loupe-php/matcher/pull/40 drops the particle in
 * Loupe's own French tokenizer, at index and query time. Once a loupe/matcher
 * release ships it:
 * - require that release;
 * - delete this class, with its calls in IndexManager::prepareForIndex() and in
 *   Searcher's query and hits handling;
 * - add an upgrade note saying indexes need `pw:search:index`.
 * Keep the separate copy in js-helper's PushwordSimpleSearch: the search.json
 * fallback has no Loupe.
 */
final class FrenchElision
{
    /** ZERO WIDTH SPACE: ICU breaks words on it, readers never see it. */
    public const string BOUNDARY = "\u{200B}";

    /** Lucene's French elision particles, followed by an apostrophe and a letter. */
    private const string PATTERN = "/(?<![\\p{L}\\p{N}])(c|d|j|l|m|n|s|t|qu|jusqu|lorsqu|puisqu|quoiqu)(['’])(?=\\p{L})/iu";

    /** `l’acompte` → `l’` + BOUNDARY + `acompte`, indexed as `l` and `acompte`. */
    public static function markForIndex(string $text): string
    {
        return self::replace('$1$2'.self::BOUNDARY, $text);
    }

    /** `l’acompte` → `acompte`. */
    public static function stripFromQuery(string $query): string
    {
        return self::replace('', $query);
    }

    /** Remove the boundaries markForIndex() added, before a value reaches a reader. */
    public static function unmark(string $text): string
    {
        return str_replace(self::BOUNDARY, '', $text);
    }

    private static function replace(string $replacement, string $text): string
    {
        $replaced = preg_replace(self::PATTERN, $replacement, $text);
        \assert(\is_string($replaced));

        return $replaced;
    }
}
