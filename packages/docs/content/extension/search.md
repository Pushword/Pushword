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
```

## Non-goals

No Elasticsearch/OpenSearch adapter (defeats the zero-infra point), no faceted
search UI beyond host/tag, no search analytics.
