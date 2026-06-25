<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;

/**
 * Hide links pointing to pages that are not yet published.
 * Converts <a href="...">text</a> to
 * <span title="..." data-status="unpublished" data-href="...">text</span>
 * so the text stays visible, the URL is not clickable for visitors, and a
 * tooltip helps debugging. A small JS snippet restores the <a> for logged-in
 * editors using the original data-href.
 */
#[AsFilter]
final readonly class HtmlUnpublishedLink implements FilterInterface
{
    public const string HTML_REGEX = '/<a(?P<before>\s+[^>]*?)href=(?P<quote>["\'])(?P<href>[^"\']+)(?P=quote)(?P<after>[^>]*)>(?P<content>(?:(?!<\/a>).)*)<\/a>/is';

    public const string UNPUBLISHED_TITLE = 'Page en cours de publication';

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

        // Batch-resolve every link target up front (one query per host) so the
        // per-link callback below hits the warm slug cache. This filter runs on the
        // fully rendered HTML, so it sees links produced downstream of LinkCollector
        // (e.g. Twig-built listing/card links) that the collector never warmed —
        // without this each link fired its own getPageBySlug(), an N+1 that cost
        // ~140 SELECTs on a single /equipe render. As the first link-resolving
        // filter it also primes the cache for HtmlVariantLink et al. that follow.
        $this->warmupLinkTargets($body, $page);

        return preg_replace_callback(
            self::HTML_REGEX,
            fn (array $match): string => $this->maybeMaskLink($match, $page),
            $body
        ) ?? $body;
    }

    private function warmupLinkTargets(string $body, Page $currentPage): void
    {
        if (false === preg_match_all(self::HTML_REGEX, $body, $matches) || [] === ($matches['href'] ?? [])) {
            return;
        }

        /** @var array<string, list<string>> $slugsByHost */
        $slugsByHost = [];
        foreach ($matches['href'] as $href) {
            $target = $this->resolveTarget($href, $currentPage);
            if (null === $target) {
                continue;
            }

            $slugsByHost[$target['host']][] = $target['slug'];
        }

        foreach ($slugsByHost as $host => $slugs) {
            $this->pageRepository->warmupSlugCacheFor($slugs, $host);
        }
    }

    /**
     * @param array<int|string, string> $match
     */
    private function maybeMaskLink(array $match, Page $currentPage): string
    {
        $href = $match['href'];
        $target = $this->resolveTargetPage($href, $currentPage);

        if (null === $target || $target->isPublished()) {
            return $match[0];
        }

        return '<span title="'.self::UNPUBLISHED_TITLE.'" data-status="unpublished" data-href="'.htmlspecialchars($href, \ENT_QUOTES | \ENT_HTML5).'">'.$match['content'].'</span>';
    }

    private function resolveTargetPage(string $href, Page $currentPage): ?Page
    {
        $target = $this->resolveTarget($href, $currentPage);
        if (null === $target) {
            return null;
        }

        return $this->pageRepository->getPageBySlug($target['slug'], $target['host']);
    }

    /**
     * Map an href to the (host, slug) it points at, or null when it is not an
     * internal page link (anchor, mailto/tel/js/data, external scheme, …).
     *
     * @return array{host: string, slug: string}|null
     */
    private function resolveTarget(string $href, Page $currentPage): ?array
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

        return ['host' => $host, 'slug' => $this->extractSlug($path)];
    }

    private function extractSlug(string $path): string
    {
        $path = (string) strtok($path, '#?');
        $slug = trim($path, '/');

        return '' !== $slug ? $slug : 'homepage';
    }
}
