<?php

namespace Pushword\Search\Service;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\SiteRegistry;

/**
 * Feeds pages into the per-host Loupe index.
 *
 * Indexes published pages only (snippets and media are out of scope for v1).
 */
final readonly class Indexer
{
    public function __construct(
        private IndexManager $indexManager,
        private TextExtractor $textExtractor,
        private PageRepository $pageRepository,
        private PushwordRouteGenerator $routeGenerator,
        private SiteRegistry $siteRegistry,
    ) {
    }

    /**
     * Rebuild the whole index for a host from scratch.
     *
     * @return int the number of indexed pages
     */
    public function reindexHost(string $host): int
    {
        // Normalise an alias to its main host; buildDocumentsForHost() performs
        // the actual switchSite() needed for rendering.
        $host = $this->siteRegistry->get($host)->getMainHost();

        $documents = $this->buildDocumentsForHost($host);
        $this->indexManager->replaceAll($host, $documents);

        return \count($documents);
    }

    /**
     * The documents indexed for a host — also the source of the static
     * `search.json` fallback.
     *
     * @return list<array<string, mixed>>
     */
    public function buildDocumentsForHost(string $host): array
    {
        $this->siteRegistry->switchSite($host);

        /** @var list<Page> $pages */
        $pages = $this->pageRepository
            ->getIndexablePagesQuery($host, '')
            ->getQuery()
            ->getResult();

        return array_map($this->buildDocument(...), $pages);
    }

    /**
     * Upsert (or remove) a single page in its host index — incremental path.
     */
    public function indexPage(Page $page): void
    {
        // The incremental path runs inside a live request/flush: render through
        // the page's own host context (Manager + canonical URL both key off
        // $page->host) without mutating the global current-site state.
        $loupe = $this->indexManager->getLoupe($page->host);

        if (! $this->isIndexable($page)) {
            if (null !== $page->id) {
                $loupe->deleteDocument($page->id);
            }

            return;
        }

        $loupe->addDocument($this->buildDocument($page));
    }

    public function removePage(int $pageId, string $host): void
    {
        if (! $this->indexManager->exists($host)) {
            return;
        }

        $this->indexManager->getLoupe($host)->deleteDocument($pageId);
    }

    private function isIndexable(Page $page): bool
    {
        return $page->isPublished()
            && ! $page->hasRedirection()
            && ! str_contains($page->getMetaRobots(), 'noindex');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocument(Page $page): array
    {
        return [
            'id' => $page->id,
            'host' => $page->host,
            'locale' => $page->locale,
            'slug' => $page->getRealSlug(),
            'url' => $this->routeGenerator->generate($page, true),
            'title' => '' !== $page->getTitle() ? $page->getTitle() : $page->getH1(),
            'h1' => $page->getH1(),
            'tags' => $page->getTagList(),
            'content' => $this->textExtractor->extract($page),
        ];
    }
}
