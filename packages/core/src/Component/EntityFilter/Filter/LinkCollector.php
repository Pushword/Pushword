<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\LinkCollectorService;

/**
 * Collects internal link slugs from raw content before Twig/Markdown processing.
 * This allows Twig functions to filter out already-linked pages from listings.
 */
#[AsFilter]
final readonly class LinkCollector implements FilterInterface
{
    /**
     * Match markdown links: [text](/slug) or [text](/slug#anchor) or [text](/slug?param).
     */
    private const string MARKDOWN_LINK_REGEX = '/\]\(\/([a-z0-9][a-z0-9\-\_\/]*?)(?:[#\?\)])/i';

    /**
     * Match HTML href: href="/slug" or href='/slug'.
     */
    private const string HTML_HREF_REGEX = '/href=["\']\/([a-z0-9][a-z0-9\-\_\/]*?)["\'\#\?]/i';

    public function __construct(
        private LinkCollectorService $linkCollector,
        private PageRepository $pageRepository,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));
        $content = (string) $propertyValue;

        $slugs = [
            ...$this->extractSlugs(self::MARKDOWN_LINK_REGEX, $content),
            ...$this->extractSlugs(self::HTML_HREF_REGEX, $content),
        ];

        foreach ($slugs as $slug) {
            $this->linkCollector->registerSlug($slug);
        }

        // Batch-load these internal link targets in a single query so the
        // downstream Html*Link filters resolve each link from the warm slug
        // cache instead of firing one getPageBySlug() per link.
        $this->pageRepository->warmupSlugCacheFor($slugs, $page->host);

        return $propertyValue;
    }

    /**
     * @return string[]
     */
    private function extractSlugs(string $pattern, string $content): array
    {
        if (false === preg_match_all($pattern, $content, $matches) || [] === $matches[1]) {
            return [];
        }

        return array_map(static fn (string $slug): string => rtrim($slug, '/'), $matches[1]);
    }
}
