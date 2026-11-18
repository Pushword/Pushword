---
title: 'Full-Text Search for Pushword CMS (SQLite, zero infra)'
h1: Search
publishedAt: '2026-05-25 10:00'
toc: true
---

Full-text search — typo tolerance, stemming, ranking — with
[Loupe](https://github.com/loupe-php/loupe), a pure-PHP, SQLite-backed engine: no
external service. The index is a portable SQLite file, so it can be built at deploy time
and shipped with [static](/extension/static-generator) and
[page-cache](/extension/page-cache) sites. It supersedes the [`search.json`](/search.json)
approach.

## Install

```shell
composer require pushword/search
php bin/console pw:search:index
```

## How it works

One **index per host** (`{index_dir}/{host}/loupe.db`), built from **published pages
only** (no snippets or media). Each body is rendered like the page itself
(`pw(page).mainContent`: Markdown, Twig, shortcodes, `pages_list`…), then stripped to
plain text. `title`, `h1` and `tags` weigh more than the body.

> **Known tradeoff.** Since the rendered body is indexed, a `pages_list` block adds the
> listed pages' titles and excerpts to this page's entry.

## Building the index

- `pw:search:index [host]` rebuilds one or every host (on demand, cron, deploy).
- Each page save or delete reindexes that page through Messenger (`incremental`).
  Unrouted, the message runs synchronously: the save absorbs one content render, and the
  page is searchable at once.
- Bulk imports (`pw:flat:sync --mode=import`, any code inside
  `PageCacheSuppressor::suppress()`) skip incremental reindexing. **Rebuild after them**:
  append `pw:search:index`, or rely on `pw:static` (`index_on_static`).

If you script many individual saves, route the messages to an async transport and run a
worker; a saved page then appears once the worker processes it:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    routing:
      'Pushword\Search\Message\ReindexPageMessage': async
      'Pushword\Search\Message\RemovePageMessage': async
```

## Querying

### Dynamic & page-cache sites

`/search?q=…` queries the index server-side and renders
`@PushwordSearch/search.html.twig` (`?format=json` for JSON). It stays dynamic alongside
the page cache, like the `liveBlock` fragments.

### Static sites

`pw:static` builds the index as part of the build (opt-out via
`index_on_static`) and emits, depending on `static_mode`:

- **`endpoint`** — the SQLite Loupe index at `search/loupe.db`, for a
  PHP/FrankenPHP search endpoint at the edge;
- **`json`** — the client-side `search.json` fallback (zero PHP), consumed by
  `PushwordSimpleSearch` (shipped in `@pushword/js-helper`). Its fields mirror
  `searchable_attributes`, so the browser ranks `title`/`h1` above the body just
  like Loupe — minus typo tolerance and stemming;
- **`both`** (default) — both.

The exported `loupe.db` is checkpointed before it is copied, so it carries its documents
without Loupe's write log.

### Recovering a damaged index

Loupe runs SQLite with `synchronous = OFF`, so a writer killed mid-write (power loss,
OOM, interrupted deploy) can leave `loupe.db` damaged (`26 file is not a database`,
`11 database disk image is malformed`). A damaged index is dropped and recreated with a
log warning — at open time when the file head is torn, during a reindex otherwise. An
index reset at open time comes back **empty**: run `pw:search:index`.

## French text and natural questions

French elisions are split off the word they precede: `acompte`, `l'acompte` and
`l’acompte` all find both "l’acompte" and "un acompte", whatever the case or accents.
Loupe keeps `l’acompte` as one word, so Pushword inserts an invisible word boundary
(U+200B) after the particle (`c d j l m n s t qu jusqu lorsqu puisqu quoiqu`) in
searchable values, and drops the particle from the query. Hits come back as written,
with the word itself highlighted. Values of attributes that are also filterable (`tags`)
stay verbatim, so filters still compare the exact value. Query the index through
`Searcher` rather than Loupe directly: it prepares the query and cleans the hits.

By default, a page matching any query word is a hit, ranked by how many it matches: a
question such as "comment fonctionne l’acompte ?" also lists every page containing
"comment" or "de". Two settings narrow it, globally or per app:

- `search_stop_words` — words left out of a query while other words remain. Compared
  without case or accents (`À` covers `a`). Keep to function words: "été" is also a
  noun.
- `search_matching_strategy` — `any` (default) or `all`. `all` requires every word:
  right for short keyword searches, too strict for most questions.

```yaml
# config/packages/pushword.yaml
pushword:
  apps:
    - hosts: [example.fr]
      search_stop_words: [le, la, les, de, des, du, un, une, et, à, au, aux, en, pour, comment]
```

Run `pw:search:index` after changing `search_stop_words`; otherwise Loupe rebuilds the
index during the next page save.

## Configuration

```yaml
# config/packages/pushword.yaml
search:
  index_dir: '%kernel.project_dir%/var/search' # one Loupe index per host
  results_per_page: 20
  search_stop_words: []          # query words to ignore, overridable per app
  search_matching_strategy: any  # any | all, overridable per app
  incremental: true       # reindex a page on save/delete (Messenger)
  index_on_static: true   # build the index during pw:static
  static_mode: both       # endpoint | json | both
  searchable_attributes: [title, h1, tags, content] # full-text fields, by descending weight
  filterable_attributes: [host, locale, tags]       # usable in filters and facets
```

## Indexing custom metadata

To search, filter or facet on your own fields (`productCode`, `difficulty`, `price`…):

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

Then filter and facet on them; every stored attribute comes back on each hit:

```php
$searcher->search(
    $host, $query,
    filter: "difficulty = 'hard' AND price < 1000",
    facets: ['difficulty', 'destination'],
);
// $result['facets'] holds the distribution per faceted attribute
```

## Non-goals

No Elasticsearch/OpenSearch adapter, no built-in faceted search UI (filters, facets and
custom attributes are exposed; the UI is yours), no search analytics.
