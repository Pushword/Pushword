---
title: Performance
h1: 'Performance & worker mode'
publishedAt: '2026-06-09 12:00'
name: Performance
---

Pushword runs fine under classic per-request PHP-FPM. It is also **safe to run in
worker mode** (a long-running process that reuses one kernel across many requests),
which removes the per-request kernel boot.

## Worker mode

Worker mode is provided by [FrankenPHP](https://frankenphp.dev/)'s `worker`
directive (or any Symfony Runtime worker). In the skeleton `Caddyfile`:

```caddyfile
php_server {
    root public/
    worker index.php 1
}
```

Between requests the runtime resets every service tagged `kernel.reset`
(`services_resetter`), exactly as a fresh FPM process would start clean.

### Why it is safe

Pushword is multi-site (host-driven) and multi-locale, so the concern with a reused
kernel is one request's state bleeding into the next. It does not, because:

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

These invariants are guarded by tests in the `worker` group
(`packages/core/tests/Worker/`), which replay two requests around the real worker
reset and assert the second request never sees stale slug/redirect/media data or a
leaked host/locale. CI runs them as a dedicated **"Worker-mode safety guard"** step.

### Memory

In production (`APP_ENV=prod`, debug off) the heap is **flat** across a long mixed
request stream — verified over hundreds of requests spanning multiple hosts and
locales. Note that in `dev`/`test` (debug on) memory grows ~50 KB/request: that is
the profiler-style collectors and the test harness's deprecation accumulator, none
of which exist in prod. Always benchmark worker memory with debug off.

### Throughput

Worker mode's win is amortizing the kernel boot. For a light page that boot is the
dominant cost, so worker mode can be several times faster; for pages doing heavy
work (large renders, many queries) the relative gain shrinks. Measure your own
workload — see `WorkerVsFpmBenchmarkTest` (`benchmark` group) for the in-process
boot-amortization figure.

### One thing to watch

`PageListener` keeps a few `static` properties (e.g. the pending-redirect queue used
during slug changes). They are process-global and not reset between requests. Normal
public `GET` traffic leaves them empty, but if you extend the admin/save path in a
worker deployment, make sure they are cleared per request.

## Running benchmarks

The benchmark suite is opt-in (excluded from CI via the `benchmark` group):

```bash
vendor/bin/phpunit --group benchmark
```

It covers static generation, repository cache warmup, search reindex, and the
worker-vs-FPM comparison. The structural query-count guards
(`packages/core/tests/Perf/`, `packages/admin/tests/Perf/`) run in normal CI and
fail if a hot path (page render, admin list) starts issuing queries that scale with
the corpus — i.e. an N+1 regression.
