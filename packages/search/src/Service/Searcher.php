<?php

namespace Pushword\Search\Service;

use Loupe\Loupe\SearchParameters;

/**
 * Queries a host's Loupe index. Cheap enough to run server-side even on
 * page-cache deployments (the /search endpoint is a dynamic exception).
 */
final readonly class Searcher
{
    /** @var list<string> */
    private const array RETRIEVED = ['id', 'url', 'slug', 'title', 'h1', 'tags', 'host', 'locale'];

    public function __construct(
        private IndexManager $indexManager,
        private int $resultsPerPage,
    ) {
    }

    /**
     * @return array{hits: list<array<string, mixed>>, query: string, totalHits: int, totalPages: int, page: int}
     */
    public function search(string $host, string $query, int $page = 1, ?int $hitsPerPage = null, ?string $locale = null): array
    {
        $empty = ['hits' => [], 'query' => $query, 'totalHits' => 0, 'totalPages' => 0, 'page' => $page];

        if ('' === trim($query) || ! $this->indexManager->exists($host)) {
            return $empty;
        }

        $parameters = SearchParameters::create()
            ->withQuery($query)
            ->withAttributesToRetrieve(self::RETRIEVED)
            ->withAttributesToHighlight(['title', 'content'])
            ->withAttributesToCrop(['content' => 40])
            ->withHitsPerPage($hitsPerPage ?? $this->resultsPerPage)
            ->withPage($page)
            ->withFilter($this->buildFilter($host, $locale));

        $result = $this->indexManager->getLoupe($host)->search($parameters);

        return [
            'hits' => $result->getHits(),
            'query' => $query,
            'totalHits' => $result->getTotalHits(),
            'totalPages' => $result->getTotalPages(),
            'page' => $result->getPage(),
        ];
    }

    private function buildFilter(string $host, ?string $locale): string
    {
        $filter = "host = '".$this->escape($host)."'";

        if (null !== $locale && '' !== $locale) {
            $filter .= " AND locale = '".$this->escape($locale)."'";
        }

        return $filter;
    }

    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
