<?php

namespace Pushword\Core\Service;

use Pushword\Core\Entity\Page;

/**
 * Classifies legacy phantom `Location:` redirect pages for a host: which ones qualify to
 * fold into a destination page's redirectFrom (internal target that resolves to an existing
 * non-redirect page) versus which must stay phantom (external, dangling, or chain target).
 *
 * Shared by the migrate command. The HTTP code is preserved when folding.
 */
final class RedirectFromResolver
{
    /**
     * @param Page[] $pages all pages of a single host
     *
     * @return array{reverse: array<string, array<string, int>>, foldedSlugs: array<string, true>}
     *                                                                                             reverse: destination slug => {old path => code}; foldedSlugs: phantom slugs folded
     */
    public function resolve(array $pages): array
    {
        $bySlug = [];
        foreach ($pages as $page) {
            $bySlug[$page->getSlug()] = $page;
        }

        $reverse = [];
        $foldedSlugs = [];
        foreach ($pages as $page) {
            if (! $page->hasRedirection()) {
                continue;
            }

            $url = $page->getRedirectionUrl();
            if (! str_starts_with($url, '/')) {
                continue; // external target — keep as phantom
            }

            $targetSlug = Page::normalizeSlug(ltrim($url, '/'));
            $target = $bySlug[$targetSlug] ?? null;
            if (null === $target || $target->hasRedirection()) {
                continue; // dangling or chained target — keep as phantom
            }

            $reverse[$targetSlug][$page->getSlug()] = $page->getRedirectionCode();
            $foldedSlugs[$page->getSlug()] = true;
        }

        return ['reverse' => $reverse, 'foldedSlugs' => $foldedSlugs];
    }
}
