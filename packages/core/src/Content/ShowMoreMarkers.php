<?php

namespace Pushword\Core\Content;

/**
 * The legacy `<!--start-show-more-->` / `<!--end-show-more-->` pair.
 *
 * One definition of what a marker line is and which lines pair with which,
 * shared by the filter that expands them and the command that rewrites them —
 * a body must not be read one way and converted another.
 */
final class ShowMoreMarkers
{
    public const string START = '<!--start-show-more-->';

    public const string END = '<!--end-show-more-->';

    /**
     * Stack-match the marker lines, innermost first. A marker left without a
     * partner is absent from the result: callers leave it alone rather than
     * letting it decide how the rest of the body is treated.
     *
     * @param list<string> $lines
     *
     * @return array<int, bool> line index => opening marker, in document order
     */
    public static function pair(array $lines): array
    {
        $openIndexes = [];
        $markers = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);

            if (self::START === $trimmed) {
                $openIndexes[] = $index;

                continue;
            }

            if (self::END !== $trimmed) {
                continue;
            }

            $startIndex = array_pop($openIndexes);
            if (null === $startIndex) {
                continue;
            }

            $markers[$startIndex] = true;
            $markers[$index] = false;
        }

        ksort($markers);

        return $markers;
    }

    /**
     * Lines that are a bare marker, paired or not. A marker an author disabled by
     * wrapping it in a Twig comment is not one of them, and neither is one a page
     * only quotes.
     *
     * @param list<string> $lines
     */
    public static function countLines(array $lines): int
    {
        return \count(array_filter(
            $lines,
            static fn (string $line): bool => self::START === trim($line) || self::END === trim($line),
        ));
    }
}
