---
title: 'Full-Text Search for Pushword CMS (SQLite, zero infra)'
h1: Search
publishedAt: '2026-05-25 10:00'
toc: true
---

Real full-text search — typo tolerance, stemming, ranking — without
Elasticsearch or any external service. It stays true to Pushword's "SQLite by
default, zero infra" stance by using [Loupe](https://github.com/loupe-php/loupe),
a pure-PHP, SQLite-backed search engine. The index is a portable SQLite file, so
it can be built at deploy time and shipped — exactly what the
[static](/extension/static-generator) and [page-cache](/extension/page-cache)
workflows need.

It upgrades the lightweight [`search.json`](/search.json) approach (page
title/url/truncated content) to ranked, typo-tolerant full-text search.

## Install

```shell
composer require pushword/search
php bin/console pw:search:index
```

## How it works

One **index per host** (`{index_dir}/{host}/loupe.db`), built from **published
pages only** (snippets and media are out of scope for v1).

For each page, the body (`content`) is rendered exactly as `raw.twig` does —
through the [EntityFilter](/) pipeline (`pw(page).mainContent`) — so Markdown,
Twig and shortcodes (`pages_list`, `pages()`, …) execute and the indexed text
matches what visitors read. The rendered HTML is then stripped to normalized
plain text (Loupe is fed text, never HTML or Markdown, which would bloat the
index and tokenize tags/URLs into noise).

`title`, `h1` and `tags` are weighted above the body, so heading structure — not
markup — drives ranking.

> **Known tradeoff.** Because the rendered body is indexed, a `pages_list` block
> injects other pages' titles/excerpts into this page's entry. Listing-block
> dedup is a later refinement.

## Building the index

Both triggers ship out of the box:

- **On demand / cron** — `pw:search:index [host]` rebuilds one or every host.
- **Incremental** — on page save/delete, a Messenger message reindexes the
  single page (mirrors the page-cache invalidation). Toggle with `incremental`.

## Querying

### Dynamic & page-cache sites

A `/search` controller queries the index server-side and renders
`@PushwordSearch/search.html.twig`. It is cheap enough to run even alongside the
no-PHP page cache (a dynamic exception, like the `liveBlock` fragments). Add
`?format=json` for a JSON response.

### Static sites

`pw:static` builds the index as part of the build (opt-out via
`index_on_static`) and emits, depending on `static_mode`:

- **`endpoint`** — the SQLite Loupe index at `search/loupe.db`, for a
  PHP/FrankenPHP search endpoint at the edge;
- **`json`** — the client-side `search.json` fallback (zero PHP);
- **`both`** (default) — both.

## Configuration

```yaml
# config/packages/pushword.yaml
search:
  index_dir: '%kernel.project_dir%/var/search' # one Loupe index per host
  results_per_page: 20
  incremental: true       # reindex a page on save/delete (Messenger)
  index_on_static: true   # build the index during pw:static
  static_mode: both       # endpoint | json | both
  searchable_attributes: [title, h1, tags, content] # full-text fields, by descending weight
  filterable_attributes: [host, locale, tags]       # usable in filters and facets
```

## Indexing custom metadata

To search, filter or facet on your own fields (e.g. a product catalog with
`productCode`, `difficulty`, `price`), do two things:

1. **Declare the attributes** in `searchable_attributes` (to rank on them) and/or
   `filterable_attributes` (to filter and facet on them).
2. **Contribute the values** with a listener on `SearchDocumentEvent`, dispatched
   once per page while its document is built:

```php
use Pushword\Search\Event\SearchDocumentEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ProductSearchDocumentListener
{
    public function __invoke(SearchDocumentEvent $event): void
    {
        $product = $this->products->forPage($event->getPage());
        if (null === $product) {
            return;
        }

        $event->setAttribute('productCode', $product->code);
        $event->setAttribute('difficulty', $product->difficulty);
        $event->setAttribute('price', $product->price);
    }
}
```

Then query with extra filters and facets — custom attributes are returned on each
hit automatically (all stored attributes are retrieved):

```php
$searcher->search(
    $host, $query,
    filter: "difficulty = 'hard' AND price < 1000",
    facets: ['difficulty', 'destination'],
);
// $result['facets'] holds the distribution per faceted attribute
```

## Non-goals

No Elasticsearch/OpenSearch adapter (defeats the zero-infra point), no built-in
faceted search UI (the building blocks — filters, facets, custom attributes — are
exposed; the UI is yours), no search analytics.
