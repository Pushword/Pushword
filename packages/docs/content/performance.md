---
title: Performance
h1: 'Performance & worker mode'
publishedAt: '2026-06-09 12:00'
name: Performance
---

Pushword runs under classic PHP-FPM and is **safe in worker mode** (one long-running
kernel serving many requests), which removes the per-request kernel boot.

## Worker mode

Use [FrankenPHP](https://frankenphp.dev/)'s `worker` directive (or any Symfony Runtime
worker). The `Caddyfile` reads it from the environment, so enabling it is one variable:

```dotenv
FRANKENPHP_WORKER_CONFIG=worker ./public/index.php 1
```

```caddyfile
php_server {
    resolve_root_symlink
    {$FRANKENPHP_WORKER_CONFIG}
}
```

It needs `composer require runtime/frankenphp-symfony`. The same variable works in the
[Docker](/docker) setup. Between requests the runtime resets every service tagged
`kernel.reset`.

### Why it is safe

One request's host, locale or cached data never leaks into the next:

- **Per-request state is re-derived every request.** `RequestContextListener`
  resolves the current site/host and locale from the incoming request on
  `kernel.request`; the translator locale is reset by Symfony's `kernel.reset`.
- **The repositories' in-memory caches are flushed by the reset.** `PageRepository`
  (slug existence set, redirect maps) and `MediaRepository` (filename index) are
  `onClear` Doctrine listeners; the worker boundary clears the EntityManager, which
  cascades to `onClear`. The media filename index is additionally invalidated on
  every Media write via a `cache.app` version counter, so it can never serve a stale
  hit — in worker mode or across FPM requests.
- **`LinkCollectorService`** is reset at the start of every request.

The `worker` test group (`packages/core/tests/Worker/`) replays two requests around the
real reset and asserts the second sees no stale slug/redirect/media data or host/locale;
CI runs it as the **"Worker-mode safety guard"** step.

### Memory

With `APP_ENV=prod` and debug off, the heap stays **flat** over hundreds of mixed
requests across hosts and locales. With debug on (`dev`/`test`) memory grows
~50 KB/request from debug collectors — benchmark worker memory with debug off.

### Throughput

The gain is the amortized kernel boot: several times faster for light pages, less for
heavy renders. `WorkerVsFpmBenchmarkTest` (`benchmark` group) measures it in-process.

### One thing to watch

`PageListener` keeps process-global `static` state (the slug-change redirect queue and
skip/reentrancy flags), so it implements `ResetInterface` and clears it at the worker
boundary. Do the same for any `static` state you add on the save path.

Two less visible shapes leak the same way. A memo keyed by page id holds the `Page` it
was built from, so a worker keeps serving a page's old body after an edit; the render
memos are layered (`ContentExtension` over `ContentPipelineFactory`) and each needs its
own reset. And a flag that an in-process static generation flips on a shared service
(`SiteConfig::setStatic()`, the router's host prefix) must be restored by `reset()`, or
every later request on that worker renders as if exporting. `WorkerModeCrossRequestTest`
replays an edit between two requests.

### Admin

The admin's services were audited for cross-request state too:

- `AdminFormFieldManager` resolves the current user lazily from `Security` on each
  call (it does not capture it at construction), so the authenticated identity —
  and the `ROLE_SUPER_ADMIN` gate in `UserRolesField` — is always the current
  request's user.
- `AdminExtension` implements `ResetInterface`; its per-host tag cache is cleared
  at the worker boundary so a newly created tag is never hidden by a stale cache.

`packages/admin/tests/Worker/AdminWorkerStateResetTest` guards them. A custom service
that captures the request, user or host at construction, or caches per-request data,
must be made lazy or `ResetInterface`.

## Running benchmarks

The `benchmark` group is opt-in (excluded from `composer test` and CI). It covers static
generation, repository cache warmup, search reindex and worker versus FPM:

```bash
./.scripts/test --benchmark                    # whole group
./.scripts/test --benchmark RepositoryBenchmarkTest
```

The query-count guards in `packages/core/tests/Perf/` and `packages/admin/tests/Perf/`
run in normal CI and fail when a hot path (page render, sitemap, admin list) issues
queries that scale with the corpus — an N+1 regression. Database comparisons:
[Database and pipeline benchmarks](/database-benchmarks).

To find where a render spends its time, use a sampling profiler such as
[Excimer](https://pecl.php.net/package/excimer) rather than Xdebug, whose shares inflate
PHP-call-heavy code and hide time spent in C functions. Confirm each gain with a
profiler-free A/B, interleaving the runs when the difference is under about 1.5 ms per
page. Keep a fresh process's single pass (what each `pw:static` worker pays, PHP and Twig
compilation included) apart from the steady state of later loops, so a one-off cost does
not pass for a per-page one.
