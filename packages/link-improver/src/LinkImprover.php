<?php

namespace Pushword\LinkImprover;

use Piedweb\LinksImprover\LinksImprover as ImproverEngine;
use Piedweb\LinksImprover\LinksManager;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Filter\FilterInterface;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\LinkCollectorService;
use Pushword\Core\Site\SiteRegistry;

/**
 * Turns the first mention of another page's name into a link to it, in the
 * rendered HTML (see {@see InternalLinkSources} for the map). Sits right after
 * Markdown in the main_content chain — {@see DependencyInjection\AddLinkImproverToFilterChainPass}
 * puts it there for every app — before the Html* post-processors, so inserted
 * links get the same multisite/unpublished/obfuscate treatment as editorial
 * ones. Inert unless the app opts in with `link_improver: true`: it rewrites
 * content, so never on by default.
 *
 * Auto links carry a bare `data-auto-link` attribute: what was inserted stays
 * distinguishable from editorial links, in the HTML and to any audit.
 */
#[AsFilter]
final readonly class LinkImprover implements FilterInterface
{
    public const string ADDED_LINK_ATTRIBUTE = 'data-auto-link';

    /**
     * `< 1` caps the total of in-content links to a ratio of the word count,
     * `>= 1` is an absolute cap. One link per 50 words stays well under the
     * density Wikipedia keeps in its running prose (1 per 20 words, measured
     * over 185k words), while leaving room above what a well linked page
     * already holds — the cap counts the existing links.
     */
    private const float DEFAULT_MAX_LINKS = 0.02;

    public function __construct(
        private InternalLinkSources $sources,
        private AddedLinksRegistry $addedLinks,
        private LinkCollectorService $linkCollector,
        private PageRepository $pageRepository,
        private SiteRegistry $apps,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        \assert(is_scalar($propertyValue));
        $content = (string) $propertyValue;

        $site = $this->apps->get($page->host);
        if ('' === $content || true !== $site->get('link_improver')) {
            return $content;
        }

        $ignoredUrls = [InternalLinkSources::url($page->slug)]; // a page never links itself
        if (true === $site->get('link_improver_ignore_homepage')) {
            $ignoredUrls[] = InternalLinkSources::url('homepage');
        }

        $rows = array_values(array_filter(
            $this->sources->getRows($page->host, $page->locale),
            static fn (array $row): bool => ! \in_array($row[0], $ignoredUrls, true),
        ));
        if ([] === $rows) {
            return $content;
        }

        $linksManager = new LinksManager($rows);
        $linksManager->reOrder(true); // longest keyword first: the most specific target wins

        $engine = new ImproverEngine($content);
        $this->indexNormalizedExistingLinks($engine, $site->getStr('base_url'));

        $maxLinks = $site->get('link_improver_max_links');
        $content = $engine->improve(
            $linksManager,
            \is_numeric($maxLinks) ? (float) $maxLinks : self::DEFAULT_MAX_LINKS,
            self::ADDED_LINK_ATTRIBUTE
        );

        $this->registerAddedLinks($engine->getAddedLinks(), $page);

        return $content;
    }

    /**
     * The engine skips a target it already finds among the content's hrefs,
     * comparing strings strictly. Feed it the `/slug` form of every href so an
     * absolute self-host link or a `#fragment`/`?query` variant still counts
     * as "already linked".
     */
    private function indexNormalizedExistingLinks(ImproverEngine $engine, string $baseUrl): void
    {
        foreach ($engine->getExistingLinks() as $href) {
            $normalized = '' !== $baseUrl && str_starts_with($href, $baseUrl) ? substr($href, \strlen($baseUrl)) : $href;
            $normalized = preg_replace('/[#?].*$/', '', $normalized) ?? $normalized;
            if ('' !== $normalized && $normalized !== $href) {
                $engine->addExistingLink($normalized);
            }
        }
    }

    /**
     * @param list<array{0: string, 1: string}> $addedLinks `[anchor, url]` pairs from the engine
     */
    private function registerAddedLinks(array $addedLinks, Page $page): void
    {
        $slugs = [];
        foreach ($addedLinks as [$anchor, $url]) {
            $this->addedLinks->record($page, $anchor, $url);

            $slug = trim($url, '/');
            if ('' === $slug) {
                continue;
            }

            $slugs[] = $slug;
            // pages_list's excludeAlreadyLinked must count these links too
            $this->linkCollector->registerSlug($slug);
        }

        if ([] !== $slugs) {
            // The Html* link filters behind us resolve each href from the slug cache
            $this->pageRepository->warmupSlugCacheFor($slugs, $page->host);
        }
    }
}
