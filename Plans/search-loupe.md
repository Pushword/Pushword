# Plan — Optional Full-Text Search (`pushword/search`, Loupe-based)

> Status: scoped / ready to execute. SQLite-native full-text, zero infra,
> compatible with the static / page-cache modes. Inspired by Sulu 3.0's
> SEAL/Loupe choice.

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

A command `pw:search:index` that iterates **published pages only** (no snippets, no
media in v1) through the existing **EntityFilter pipeline** to get rendered text,
then feeds Loupe (id, host, title, h1, url, content, tags). Both triggers ship in v1:
- on demand / cron, and
- hooked into the existing **Messenger single-page invalidation** already used by
  page-cache (`packages/static-generator`) so the index updates incrementally on
  save/delete.

**One index per host** (`{host}.db`), for clean static shipping. Index files stored
via Flysystem with the local adapter by default (swappable to remote).

#### Text extraction — render chrome-less, then strip to plain text

Index **per-page fields, never full templated HTML**: feed Loupe `title`, `h1`,
`content`, `tags`, `url`, `host` as separate attributes. `title`/`h1`/`tags` are
weighted above the body, so heading structure drives ranking — not markup in `content`.

The `content` field is produced by:

1. **Render through `raw.twig`** (`packages/core/src/templates/page/raw.twig`) — it
   outputs `{{ pw(page).mainContent|raw }}` with **no layout** (no `extends`). This
   excludes navbar/header/footer/breadcrumb (all siblings of the `body` block in
   `page_default.html.twig`) *and* the breadcrumb JSON-LD (template-level, via
   `AppExtension::generateBreadcrumbJsonLd`). Rendering through Twig also **executes
   the body's Twig/shortcodes** — `pages_list`, `pages()`, conditionals, includes —
   so the indexed text matches what visitors actually see (`Filter/Twig.php`).
2. **Strip to normalized plain text** — `striptags` + strip `<script>`/`<style>`
   **element contents** (not just tags, so a hand-embedded `ld+json` block can't leak
   raw JSON) + decode HTML entities + collapse whitespace.

Loupe is fed **plain text, never HTML or markdown**: HTML bloats the index 2–4× and
tokenizes tags/classes/URLs into noise matches and messy highlight snippets; markdown
adds no tokenizer benefit and still carries link/image syntax.

#### Known tradeoff — dynamic listing blocks (deferred)

Because we index the **rendered** body (option 1), a `pages_list` block injects other
pages' titles/excerpts into this page's entry → cross-page duplication, plus staleness
(the incremental hook reindexes the *saved* page, not listing pages that reference it).
Accepted for v1; listing-block dedup/exclusion is a known later refinement. A shared
snippet/block inside `mainContent` is likewise indexed (snippets deferred anyway).

### Querying — two modes

1. **Dynamic / page-cache sites**: a `/search` controller + Twig results template
   queries the Loupe index server-side. Cheap enough to run even alongside the
   no-PHP page cache (the search endpoint is a dynamic exception, like the
   `liveBlock` fragments already are).
2. **Fully static sites** (`pw:static`): **both paths, configurable** —
   - ship the SQLite Loupe index + a tiny PHP/FrankenPHP search endpoint (preferred
     when a PHP runtime is available at the edge), and
   - fall back to enriching the existing `search.json` (client-side search) when no
     server is available. Keep `search.json` as the zero-PHP fallback path.
   A config flag selects which the static build emits.

### Integration with static generator

`pw:static` runs `pw:search:index` as part of the build **by default** (opt-out via
config) and copies the index/endpoint, mirroring how it already emits
Apache/GitHub-Pages/Caddy artifacts.

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

## Reference / first consumer — the docs site (`pushword.piedweb.com`)

The docs site is the dogfooding target and validates the whole pipeline end-to-end.
It is a normal Pushword host: pages are the flat markdown in
`packages/skeleton/content/pushword.piedweb.com/`, synced to Page entities via
`pw:flat:sync`, and configured as an app in `pushword.yaml`. So it indexes into a
per-host `pushword.piedweb.com.db` with no special-casing, and **upgrades the existing
lightweight `search.json.md`** (title/url/truncated content) to ranked, typo-tolerant
full-text search.

Good fit because: docs are code-heavy → rendered `<pre><code>` survives `striptags`,
so searching commands / config keys / function names works; and docs are prose/reference,
not `pages_list` index pages, so the listing-block tradeoff is a non-issue here.

Reindex trigger for docs: content arrives via **`pw:flat:sync`**, not admin edits, and
the site is statically deployed — so reindex is driven by **sync + the static build
step** (`pw:static` runs `pw:search:index` by default), not interactive Page saves.

## Integration points

- EntityFilter pipeline (`packages/core/src/Component/EntityFilter/`) for rendered
  text extraction — reuse, don't reimplement.
- Messenger invalidation hooks in `packages/static-generator` for incremental index.
- `pw:static` build step.
- Flysystem for index storage.

## Resolved decisions

- **Multi-host**: one index per host (`{host}.db`).
- **Static fallback**: both — PHP/FrankenPHP endpoint *and* client-side `search.json`,
  selectable via config per deployment.
- **Reindex**: both on-demand/cron command and incremental Messenger hook in v1.
- **V1 scope**: dynamic `/search` *and* full static-generator integration.
- **Indexed content**: published pages only (snippets/media deferred).
- **Index storage**: Flysystem, local adapter by default.
- **Static build**: `pw:static` runs the indexer by default, opt-out via config.

## Open question (resolve during implementation)

- Loupe version pinning and index size on large sites — verify against the latest
  stable `loupe/loupe` and benchmark a large host before locking the constraint.

## Non-goals

- No Elasticsearch/OpenSearch adapter (defeats the zero-infra point), no faceted
  search UI beyond host/tag, no search analytics.
