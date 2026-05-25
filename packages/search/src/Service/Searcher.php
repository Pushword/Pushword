<?php

namespace Pushword\Search\Service;

use Loupe\Loupe\SearchParameters;

/**
 * Queries a host's Loupe index. Cheap enough to run server-side even on
 * page-cache deployments (the /search endpoint is a dynamic exception).
 */
final readonly class Searcher
{
    public function __construct(
        private IndexManager $indexManager,
        private int $resultsPerPage,
    ) {
    }

    /**
     * `*` retrieves every stored attribute, so custom fields contributed via
     * SearchDocumentEvent are returned without further configuration.
     *
     * @param string       $filter an extra Loupe filter expression, AND-ed with host/locale (e.g. "difficulty = 'hard' AND price < 1000")
     * @param list<string> $facets filterable attributes to return a distribution for
     *
     * @return array{hits: list<array<string, mixed>>, facets: array<string, array<string, int>>, query: string, totalHits: int, totalPages: int, page: int}
     */
    public function search(string $host, string $query, int $page = 1, ?int $hitsPerPage = null, ?string $locale = null, string $filter = '', array $facets = []): array
    {
        $empty = ['hits' => [], 'facets' => [], 'query' => $query, 'totalHits' => 0, 'totalPages' => 0, 'page' => $page];

        if ('' === trim($query) || ! $this->indexManager->exists($host)) {
            return $empty;
        }

        $parameters = SearchParameters::create()
            ->withQuery($query)
            ->withAttributesToRetrieve(['*'])
            ->withAttributesToHighlight(['title', 'content'])
            ->withAttributesToCrop(['content' => 40])
            ->withHitsPerPage($hitsPerPage ?? $this->resultsPerPage)
            ->withPage($page)
            ->withFacets($facets)
            ->withFilter($this->buildFilter($host, $locale, $filter));

        $result = $this->indexManager->getLoupe($host)->search($parameters);

        return [
            'hits' => $result->getHits(),
            'facets' => $result->getFacetDistribution(),
            'query' => $query,
            'totalHits' => $result->getTotalHits(),
            'totalPages' => $result->getTotalPages(),
            'page' => $result->getPage(),
        ];
    }

    private function buildFilter(string $host, ?string $locale, string $extra): string
    {
        $filter = "host = '".$this->escape($host)."'";

        if (null !== $locale && '' !== $locale) {
            $filter .= " AND locale = '".$this->escape($locale)."'";
        }

        if ('' !== trim($extra)) {
            $filter .= ' AND ('.$extra.')';
        }

        return $filter;
    }

    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
