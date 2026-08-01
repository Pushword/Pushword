# Plan — Page cache invalidation beyond `Page` (revised after adversarial review)

## Goal

Make the `cache: static` HTML in `public/cache/{host}/` stale-free for changes
that are **not** a `Page` write: snippet edits, media edits, template edits, and
page→page dependencies (listings, navs, breadcrumbs). The same primitive must
also fix `pw:static --incremental` for full-static fleets (GA-class), which
share the identical staleness bug with zero messaging involved.

The target is not a precise dependency graph. It is a **single, trustworthy
answer** to "is this page's output stale", consumed by every path that asks the
question.

## Current state (baseline — each point verified in code)

1. **`PageCacheInvalidator` binds to `Page` only**
   (`packages/static-generator/src/Cache/PageCacheInvalidator.php:14-16`).
   Nothing else in the codebase invalidates cached HTML.

2. **The blind spot is wider than snippet/media/template**: other pages
   (`pages()`, `card_list()`, `pages_list()`, pager, `linked_slugs`, parent
   chains), reviews (`reviews()` / `reviewList()`), and the asset manifest.

3. **The same wrong staleness model exists twice.**
   `GenerationStateManager::needsRegeneration()` compares only `page.updatedAt`,
   so `pw:static --incremental` skips exactly the pages the invalidator misses.

4. **A blunt sweep is cheaper than it looks.** `PageGenerator` skips write +
   compression when the render is byte-identical and renders are
   byte-deterministic: a sweep costs CPU (~11ms warm/page) but zero disk churn
   and zero CDN invalidation for unaffected pages.

5. **Reusable seams already exist**: `MediaCacheInvalidationListener` (version
   counter in `cache.app`), `ElementAdmin::clearTwigCache()`,
   `PageCacheSuppressor`, `PageListener` (preUpdate-capture/postUpdate-act
   pattern with kernel.reset safety).

6. **The two stores have different lifetimes.** `var/.static-generation-state.json`
   survives `cache:clear`; `cache.app` does not. A `cache.app` wipe must read as
   "everything stale" — hence the epoch is a random token, never a counter.

### Verified during review (constraints the design must honor)

- **Messenger is sync-only in dev-app** (`framework.yaml:15-18`) and Symfony
  runs unrouted messages inline. `DelayStamp` is ignored on the sync transport.
- **GA-class installs have real async infra** (doctrine transport, systemd
  workers, `DelayStamp` in production use, and they already route a Pushword
  message — `ConsumePendingExportMessage` — async). But they run full
  `pw:static`, **not** cache mode: `isCacheSite()` is false there, so messages
  never fire for them. Their fix is the epoch + their existing generation runs.
- **A sweep must never run inside a Doctrine flush**: `PagesGenerator` calls
  `em->clear()` every 2 pages, which corrupts an in-progress UnitOfWork. On the
  sync transport a message dispatched from `postUpdate` runs inline, inside the
  flush. Sweep dispatch therefore happens on `kernel.terminate` /
  `console.terminate`, never from a lifecycle event.
- **Package dependencies forbid direct message dispatch from bump sources**:
  conversation and snippet depend on core only, `MediaCacheInvalidationListener`
  lives in core. Core cannot reference static-generator classes.
- **Slug renames already self-heal**: `PageListener` auto-creates a redirection
  page at the old slug; its own `postPersist` refresh overwrites the stale file.
- **The trigger filter survives the XML templates only as long as
  `sitemap.xml.twig:6` keeps `lastmod` commented out** (rss items expose only
  h1/slug/publishedAt — all in the metadata allowlist). If that TODO is ever
  done, `updatedAt` becomes listing-relevant on every save.

## Prior art: Astro (unchanged from v1, conclusions only)

Astro rebuilds everything; incremental build is an open RFC scoped to bundler
caching. Their per-entry digest validates our byte-identical skip; their CDN/ISR
answer relocates the tag problem without solving it. The blunt sweep **is** the
industry answer. Pushword is better positioned: we own the render services.

## Architecture (one seam per direction)

```
bump sources (any package)          core                         static-generator
──────────────────────────          ────                         ────────────────
Snippet listener      ─┐
Media listener        ─┤─▶ RenderEpoch::bump(host?) ─▶ RenderEpochBumpedEvent
Template editor       ─┤        (cache.app,                      │
Review listener       ─┤       random token,                     ▼
Page listing-relevant ─┘      per main host)          bump listener: queue host
                                                      (cache-mode apps only)
                                                                 │ kernel/console.terminate
                                                                 ▼
                                                      HostCacheRefreshMessage(host)
                                                      [+DelayStamp on async routes]
                                                                 │
                                                                 ▼
consumers                                             handler: debounce
─────────                                             (sweptEpoch === current → stop)
pw:static --incremental (cron; GA-class baseline) ─┐             │
pw:cache:clear warm                                ├─▶ StaticAppGenerator::generate
message handler (cache-mode fast lane) ────────────┘   samples epoch at start,
                                                       stamps pages with the sample,
                                                       records sample as sweptEpoch
```

## Phase 0 — One staleness source of truth

**`Pushword\Core\Cache\RenderEpoch`** — `get(string $host): string` /
`bump(?string $host = null): void`, keyed per **main host**. `bump()` writes
`bin2hex(random_bytes(8))` and dispatches `RenderEpochBumpedEvent` with the
resolved main-host list (`null` = all apps). `get()` is
get-or-create-and-persist on a miss.

**Storage is a dedicated FilesystemAdapter under `kernel.cache_dir`
(`pw.render_epoch_dir`), NOT `cache.app`.** Found on the altimood bench, not in
review: altimood configures `cache.app: cache.adapter.apcu`. APCu is
per-process and absent on CLI, so with cache.app storage every process minted
its own token — parent, each generation worker and each successive run
disagreed, incremental regenerated all pages on every pass, and a web-side bump
was invisible to the CLI cron. The dedicated pool keeps the two properties that
matter: shared by web and CLI of one env, and wiped by `cache:clear` so a
deploy invalidates everything. Tests override the dir per worker
(`render_epoch_dir` in `test/pushword.php`) because the test kernel's cache dir
is shared across ParaTest workers.

- *Random token, not a counter*: a wiped pool must always read as stale (a reset
  counter is indistinguishable from a current one — second deploy silently kills
  the cache). Not `microtime()`: equality-only comparison, no float/skew traps.
- *Persist on miss*: otherwise every call mints a new token and regeneration
  never converges.

**Sampling semantics (load-bearing, easy to get wrong):**

- The generator samples the epoch **once per host per process, at generation
  start**, stamps every page it writes with that *sampled* value, and records
  the *sampled* value as the host's `sweptEpoch` on success. Never stamp or
  record the then-current epoch: a mid-sweep bump would mark pages rendered
  against pre-bump content as fresh — stale forever.
- Worker subprocesses sample independently: a worker that starts after a bump
  renders post-bump content, so its (newer) sample is correct for its pages.

**`GenerationStateManager`**: page entries gain `epoch`; host entries gain
`sweptEpoch`. `needsRegeneration()` is stale when `pageUpdatedAt` differs **or**
stored epoch ≠ current epoch. Legacy entries (`epoch` absent) read as stale —
one-time full regen after deploying this, which is correct anyway.

**Concurrency**: `StaticAppGenerator` takes a per-host flock
(`symfony/lock` FlockStore — released on process death, no TTL staleness)
around host generation, covering message-handler, cron, and manual runs alike.
Concurrent workers were already safe (per-worker state files merged by the
parent); concurrent *runs* clobbering `.static-generation-state.json` were not.

## Phase 1 — Route non-`Page` changes to the epoch

| Source | Seam | Scope | Events |
|---|---|---|---|
| Snippet | new listener in pushword/snippet | its host; all when host-less | persist, update, remove |
| Media | extend `MediaCacheInvalidationListener` | all hosts | **update, remove only** — a fresh upload cannot be referenced by already-cached HTML; bumping on persist would sweep every host per upload |
| Templates | `ElementAdmin` save/delete (next to `clearTwigCache()`) | all hosts |
| Config / assets | out of scope — the deploy already runs `pw:cache:clear` | — |

All bump sources check `PageCacheSuppressor` first (bulk flat import stays one
`pw:cache:clear`, not N sweeps).

**Consumer plumbing (static-generator only):**

- `RenderEpochBumpedEvent` listener collects hosts whose app is cache-mode into
  a request-scoped queue (`ResetInterface` for worker mode).
- On `kernel.terminate` / `console.terminate` the queue dispatches one
  `HostCacheRefreshMessage(string $host)` per host — after the response/command,
  so a sync-transport sweep can never run inside a flush or block the response
  body, and `em->clear()` in the generator can't detach entities a controller
  still holds.
- Handler: resolve app → not cache-mode → return; `sweptEpoch === current epoch`
  → return (the debounce); else `generate($host, incremental: true)`. Duplicate
  messages cost one skip-all pass at worst.
- No timestamp in the payload. Epoch equality subsumes the debounce and has no
  completed-vs-started blind window.

**Deployment matrix** (goes in `page-cache.md`):

- Cache-mode + async routing (route `HostCacheRefreshMessage` like GA routes
  its messages): debounced background sweep ≤ ~1min after edit.
- Cache-mode, no routing: sweep runs in `terminate` after the response —
  correct, but the PHP worker is busy for the sweep duration; fine for small
  hosts, route async past a few hundred pages.
- Full-static (GA-class): messages never fire; cron
  `pw:static --incremental` picks bumps out of the durable state file. This is
  the baseline consumer; messages are the opt-in fast lane.

## Phase 2 — Page → page staleness

Keep the instant per-page refresh, and additionally **bump the host epoch**
when the change can affect another page's output. The bump is what makes the
sweep regenerate the *other* pages — without it the incremental generator skips
everything but the edited page and the phase is a no-op.

Trigger filter (in `PageCacheInvalidator`, `preUpdate` capture → `postUpdate`
act, mirroring `PageListener`'s pending pattern incl. `reset()`):

- **Metadata fields**: `title`, `h1`, `name`, `slug`, `parentPage`,
  `publishedAt`, `weight`, `locale`, `mainImage`, `host` — what cards, navs,
  feeds and sitemaps render of *other* pages.
- **Collected link set**: diff `LinkCollector` extraction over old vs new
  `mainContent` (regexes exposed as a static helper) — feeds `linked_slugs`,
  `is_slug_linked`, `exclude_linked`, `contains_link_to` on other pages.
- Gate: only when the page is published now or `publishedAt` is in the
  changeset (draft edits never sweep).
- `postPersist` of a published page and `preRemove` of a published page are
  unconditionally listing-relevant.

Prose edits that touch no link stay on the instant single-page path only.

Bump happens even for non-cache-mode hosts (it feeds cron incremental on
GA-class sites); only the *message* is cache-mode-gated, inside the Phase 1
listener.

## Phase 3 — Reviews join the standard machinery

A conversation listener bumps the epoch when a review's published state is
involved (persist published / publication change / remove published), resolving
the host from the review's referring page, `bump(null)` as fallback. Suppressor-
checked like every other source.

**The v1 live-tail deferral is dropped.** Rationale: with the epoch machinery in
place, the static aggregate + visible reviews refresh via the normal sweep
within the debounce window; deferring the paginated tail removes **no**
invalidation trigger (the head still changes with every review), while costing a
new public endpoint and indexable text. Server islands remain the right tool for
per-user content (already used for admin buttons) — not for content whose only
sin is changing sometimes.

## Deferred — the tag table (Phase 4)

Unchanged from v1: request-scoped `RenderDependencyCollector` → tag table →
targeted refresh messages, capped with fallback to a host sweep. Build only if
measurement shows sweep latency hurting a real large host. Phase 0 gives it a
single seam (`needsRegeneration`) to plug into later.

## Ordering and risk

- Phase 0 is a hard prerequisite; Phases 1–3 all ride it. 1, 2, 3 are
  independent of each other.
- Cost profile change (Phase 2): a listing-relevant save on a big host means a
  background sweep within a minute. The filter drops the majority of saves; a
  20k-page host without async routing should route the message or rely on cron.

**Defects that must not be re-introduced** (each ships a cache that silently
stops updating or a save that blocks):

1. A counter-shaped `RenderEpoch` — dead after the second deploy.
2. An epoch store that web and CLI don't share (`cache.app` on APCu) — every
   process mints its own token and incremental generation never converges.
3. Stamping/recording the *current* epoch instead of the *sampled* one — a
   mid-sweep bump marks pre-bump renders fresh forever.
4. A page save that dispatches the sweep but doesn't bump — the incremental
   generator skips everything and the sweep is a no-op.
5. Dispatching sweep messages from a Doctrine lifecycle event — inline sync
   handling runs `em->clear()` inside the flush.

Per phase: tests alongside (`PageCacheInvalidatorTest` is the model), then
`composer stan` / `composer rector` / `composer test` and a cache clear.

## Bench (required before calling it done)

- dev-app: no-op sweep (epoch current), full sweep after a snippet bump
  (byte-identical path), per-save overhead of the trigger filter.
- Real data: ../altimood (sandboxed copy — never mutate the live project's
  `var/` or static output): full and incremental sweep timings at real page
  count.
