<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
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
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));
        $content = (string) $propertyValue;

        $this->collectFromMarkdownLinks($content);
        $this->collectFromHtmlHrefs($content);

        return $propertyValue;
    }

    private function collectFromMarkdownLinks(string $content): void
    {
        $this->collectSlugsFromPattern(self::MARKDOWN_LINK_REGEX, $content);
    }

    private function collectFromHtmlHrefs(string $content): void
    {
        $this->collectSlugsFromPattern(self::HTML_HREF_REGEX, $content);
    }

    private function collectSlugsFromPattern(string $pattern, string $content): void
    {
        if (false === preg_match_all($pattern, $content, $matches) || [] === $matches[1]) {
            return;
        }

        foreach ($matches[1] as $slug) {
            $this->linkCollector->registerSlug(rtrim($slug, '/'));
        }
    }
}
