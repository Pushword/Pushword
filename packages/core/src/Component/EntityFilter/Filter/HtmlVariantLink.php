<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;

/**
 * Consolidate internal links pointing to a variant page onto its master:
 * rewrite <a href="/variant">…</a> to
 * <a href="{master-url}" data-variant="/variant">…</a>.
 *
 * Crawlers and no-JS visitors follow href = the master (internal link juice
 * consolidated, variant kept out of the index); the opt-in progressive
 * enhancement helper (pushword/js-helper) loads the variant from data-variant.
 *
 * Runs last among the link filters so nothing reconstructs the <a> afterwards
 * (which would drop the data-variant hook); the route generator already makes
 * the master URL host-aware.
 */
#[AsFilter]
final readonly class HtmlVariantLink implements FilterInterface
{
    public function __construct(
        private PageRepository $pageRepository,
        private PushwordRouteGenerator $routeGenerator,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));
        $body = (string) $propertyValue;

        if (! str_contains($body, '<a ')) {
            return $body;
        }

        // No variant on this host → nothing to consolidate, skip the per-link lookups.
        if (! $this->pageRepository->hasVariant($page->host)) {
            return $body;
        }

        return preg_replace_callback(
            HtmlUnpublishedLink::HTML_REGEX,
            fn (array $match): string => $this->maybeRewriteVariantLink($match, $page),
            $body
        ) ?? $body;
    }

    /**
     * @param array<int|string, string> $match
     */
    private function maybeRewriteVariantLink(array $match, Page $currentPage): string
    {
        $target = $this->resolveTargetPage($match['href'], $currentPage);

        $master = $target?->getVariantOf();
        if (null === $master) {
            return $match[0];
        }

        $quote = $match['quote'];
        $masterUrl = htmlspecialchars($this->routeGenerator->generate($master), \ENT_QUOTES | \ENT_HTML5);
        $variantUrl = htmlspecialchars($match['href'], \ENT_QUOTES | \ENT_HTML5);

        return '<a'.$match['before'].'href='.$quote.$masterUrl.$quote.$match['after']
            .' data-variant='.$quote.$variantUrl.$quote.'>'.$match['content'].'</a>';
    }

    private function resolveTargetPage(string $href, Page $currentPage): ?Page
    {
        if ('' === $href
            || str_starts_with($href, '#')
            || str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:')
            || str_starts_with($href, 'data:')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $parts = parse_url($href);
            $host = $parts['host'] ?? '';
            if ('' === $host) {
                return null;
            }

            $path = $parts['path'] ?? '';
        } elseif (str_starts_with($href, '/')) {
            $host = $currentPage->host;
            $path = $href;
        } else {
            return null;
        }

        return $this->pageRepository->getPageBySlug($this->extractSlug($path), $host);
    }

    private function extractSlug(string $path): string
    {
        $path = (string) strtok($path, '#?');
        $slug = trim($path, '/');

        return '' !== $slug ? $slug : 'homepage';
    }
}
