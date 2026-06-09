<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;

/**
 * Rewrite internal links pointing at a page's former slug (registered in its
 * redirectFrom) to its current slug: <a href="/old-name">…</a> becomes
 * <a href="/new-name">…</a>. Mirrors how a renamed media file is still resolved
 * by its fileNameHistory at render — the link targets the page directly instead
 * of relying on a 301 hop at navigation time.
 *
 * Runs right after Markdown so the downstream link filters (multisite,
 * unpublished, variant) see the corrected slug. Only root-relative links are
 * rewritten — the canonical case authored in content (`[x](/old-name)`).
 */
#[AsFilter]
final readonly class HtmlRedirectFromLink implements FilterInterface
{
    public function __construct(
        private PageRepository $pageRepository,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));
        $body = (string) $propertyValue;

        if (! str_contains($body, '<a ')) {
            return $body;
        }

        return preg_replace_callback(
            HtmlUnpublishedLink::HTML_REGEX,
            fn (array $match): string => $this->maybeRewriteLink($match, $page),
            $body
        ) ?? $body;
    }

    /**
     * @param array<int|string, string> $match
     */
    private function maybeRewriteLink(array $match, Page $currentPage): string
    {
        $newHref = $this->rewriteHref($match['href'], $currentPage);

        if (null === $newHref) {
            return $match[0];
        }

        $quote = $match['quote'];
        $newHref = htmlspecialchars($newHref, \ENT_QUOTES | \ENT_HTML5);

        return '<a'.$match['before'].'href='.$quote.$newHref.$quote.$match['after'].'>'.$match['content'].'</a>';
    }

    private function rewriteHref(string $href, Page $currentPage): ?string
    {
        if (! str_starts_with($href, '/')) {
            return null;
        }

        // Split off #fragment / ?query so only the path's slug is resolved and
        // the suffix is reattached to the rewritten URL.
        $suffixStart = strcspn($href, '#?');
        $slug = trim(substr($href, 0, $suffixStart), '/');
        if ('' === $slug) {
            $slug = 'homepage';
        }

        $currentSlug = $this->pageRepository->resolveRedirectFromSlug($slug, $currentPage->host);
        if (null === $currentSlug) {
            return null;
        }

        return '/'.$currentSlug.substr($href, $suffixStart);
    }
}
