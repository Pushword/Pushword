# Plan — Optional Full-Text Search (`pushword/search`, Loupe-based)

> Status: idea / draft. SQLite-native full-text, zero infra, compatible with the
> static / page-cache modes. Inspired by Sulu 3.0's SEAL/Loupe choice.

## Goal

Offer real full-text search as an **optional bundle**, without forcing
Elasticsearch or any external service — staying true to Pushword's
"SQLite by default, zero infra" stance. Must work for both dynamic sites and
**static / page-cache** deployments.

## Current state (baseline)

- Lightweight only: `pages_list` query DSL (admin), media `altSearch` column, and a
  generated `search.json` (a Twig page emitting page title/url/slug/truncated
  content) — see `packages/docs/content/search.json.md`.
- No full-text ranking, typo tolerance, or stemming.

## Why Loupe

- Pure-PHP, **SQLite-backed** full-text engine (typo tolerance, ranking, filters) —
  same storage philosophy as Pushword. No daemon, no JVM.
- Its index is a portable SQLite file → can be built at deploy time and shipped,
  which is exactly what the static workflow needs.

## Design

### Index building

A command `pw:search:index` that iterates published pages (and optionally snippets)
through the existing **EntityFilter pipeline** to get rendered text, then feeds
Loupe (id, host, title, h1, url, content, tags). Run:
- on demand / cron, and
- hooked into the existing **Messenger single-page invalidation** already used by
  page-cache (`packages/static-generator`) so the index updates incrementally on
  save/delete.

Index file stored via Flysystem (local by default), per host.

### Querying — two modes

1. **Dynamic / page-cache sites**: a `/search` controller + Twig results template
   queries the Loupe index server-side. Cheap enough to run even alongside the
   no-PHP page cache (the search endpoint is a dynamic exception, like the
   `liveBlock` fragments already are).
2. **Fully static sites** (`pw:static`): two options —
   - ship the SQLite Loupe index + a tiny PHP/FrankenPHP search endpoint, or
   - fall back to enriching the existing `search.json` (client-side search) when no
     server is available. Keep `search.json` as the static fallback path.

### Integration with static generator

`pw:static` should optionally run `pw:search:index` as part of the build and copy
the index/endpoint, mirroring how it already emits Apache/GitHub-Pages/Caddy
artifacts.

## Package skeleton (match existing bundles)

```
packages/search/
  composer.json     # name: pushword/search, require pushword/core, loupe/loupe
  src/PushwordSearchBundle.php
  src/Command/IndexCommand.php          # pw:search:index
  src/Controller/SearchController.php    # /search
  src/Service/Indexer.php / Searcher.php
  src/EventSubscriber/...                # incremental reindex on page save/delete
  src/templates/search.html.twig
  src/config/services.php
  tests/
```

## Integration points

- EntityFilter pipeline (`packages/core/src/Component/EntityFilter/`) for rendered
  text extraction — reuse, don't reimplement.
- Messenger invalidation hooks in `packages/static-generator` for incremental index.
- `pw:static` build step.
- Flysystem for index storage.

## Open questions

- Loupe maturity / version pinning, and index size on large sites.
- Multi-host: one index per host vs one index with a `host` filter facet. Lean
  per-host for clean static shipping.
- Static fallback: is `search.json` client-side good enough when there's truly no
  PHP, or do we always assume FrankenPHP/PHP is available? Decide per deployment.

## Non-goals

- No Elasticsearch/OpenSearch adapter (defeats the zero-infra point), no faceted
  search UI beyond host/tag, no search analytics.
