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

    public const string INLINE_DIRECTIVE = 'page-scanner-ignore';

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
     * @return string[]
     */
    public static function forPage(Page $page): array
    {
        $property = $page->getCustomProperty(self::PAGE_PROPERTY);

        $patterns = [];
        foreach (\is_array($property) ? $property : [$property] as $declared) {
            if (\is_string($declared) && '' !== ($pattern = trim($declared))) {
                $patterns[] = $pattern;
            }
        }

        return [...$patterns, ...InlineDirective::patterns($page->mainContent, self::INLINE_DIRECTIVE)];
    }

    private static function matchesOne(string $pattern, string $code, string $message): bool
    {
        return fnmatch($pattern, $code) || fnmatch($pattern, strip_tags($message));
    }
}
