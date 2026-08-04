<?php

namespace Pushword\PageScanner\Service;

use Pushword\Core\Entity\Page;

/**
 * What a site chose not to be told about, on the three surfaces that can say so.
 *
 * The `errors_to_ignore` config speaks for the whole installation, globally or scoped
 * to a route (`host/slug: pattern`), and is applied when results are read — editing it
 * takes effect without a new scan. A page speaks for itself, through a
 * `<!-- page-scanner-ignore: … -->` comment in its content or a `pageScanErrorsToIgnore`
 * custom property; those are applied while scanning, so they follow the content they
 * live in and take effect on the next scan.
 *
 * A pattern is an fnmatch tested against the error code first, then against its
 * plain-text message — a code silences a family of findings, a message pins one.
 */
final class ErrorIgnoreRules
{
    public const string PAGE_PROPERTY = 'pageScanErrorsToIgnore';

    private const string INLINE_PATTERN = '/<!--\s*page-scanner-ignore:(.*?)-->/is';

    /**
     * @param string[] $configPatterns
     */
    public static function isIgnored(array $configPatterns, string $route, string $code, string $message): bool
    {
        foreach ($configPatterns as $pattern) {
            $parts = explode(': ', $pattern, 2);

            if (isset($parts[1]) && ! fnmatch($parts[0], $route)) {
                continue;
            }

            if (self::matchesOne($parts[1] ?? $pattern, $code, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $patterns
     */
    public static function matches(array $patterns, string $code, string $message): bool
    {
        return array_any($patterns, static fn (string $pattern): bool => self::matchesOne($pattern, $code, $message));
    }

    /**
     * The patterns a page declares about itself.
     *
     * Read from the content rather than from the rendered HTML: a page whose render
     * failed produces no HTML, and silencing that failure is exactly what an editor
     * would want to write there.
     *
     * @return string[]
     */
    public static function forPage(Page $page): array
    {
        $declared = $page->getCustomProperty(self::PAGE_PROPERTY);

        $patterns = [];
        foreach (\is_array($declared) ? $declared : [$declared] as $pattern) {
            if (\is_string($pattern) && '' !== trim($pattern)) {
                $patterns[] = trim($pattern);
            }
        }

        preg_match_all(self::INLINE_PATTERN, $page->mainContent, $matches);
        foreach ($matches[1] as $declaration) {
            foreach (explode(',', $declaration) as $pattern) {
                if ('' !== trim($pattern)) {
                    $patterns[] = trim($pattern);
                }
            }
        }

        return $patterns;
    }

    private static function matchesOne(string $pattern, string $code, string $message): bool
    {
        return fnmatch($pattern, $code) || fnmatch($pattern, strip_tags($message));
    }
}
