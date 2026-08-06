---
name: test-triage
description: Decide whether a failing Pushword test is a real regression or a known pre-existing flake. Use when composer test, composer test-mariadb, or CI goes red and you need to know if your change caused it.
---

# Is this failure mine?

The suite has a cluster of pre-existing flakes that **hop between parallel shards**, so
re-running surfaces a different one each time and chasing green by re-running does not
converge. Check the failing test against this dossier before investigating your change.

## Triage order

1. Is the failing test named below? A live flake is almost certainly pre-existing noise;
   a fixed one failing afresh is real — read its post-mortem in
   [references/resolved.md](references/resolved.md) before starting over, the dead ends
   are recorded there so they are not walked twice.
2. Re-run that test **in isolation**. Passing in isolation but failing under load means
   pollution, not a portability bug.
3. Before blaming a cross-worker race, look for the *class* that poisons it and re-run
   just that pair: `composer test-filter 'ThatClass|FailingClass'`. Workers get their own
   DB, media, var and flat content dir (`$testBaseDir` carries `w<token>`), so a fixture
   another class destroyed and never restored is the likelier cause — and it reads
   exactly like a race, because paratest distributes by class and only some layouts put
   the two together. (`LinkedDocsScannerTest`'s redirection failure was exactly this:
   `PageSyncTest` imported a redirection.csv omitting the fixtures' rows, and `import()`
   deletes what the csv omits.)
4. On CI, before believing several jobs failed at once: the matrix is fail-fast, so one
   flaked shard cancels its siblings and the whole run reads red — check `conclusion` on
   the siblings via `gh api repos/:owner/:repo/actions/jobs/<id>`.
5. Confirm the tests *your* change touches pass deterministically — repeat-run locally,
   then `composer test-mariadb`.

## Live flakes

**`PageScanner\Tests\Api\PageScanApiControllerTest`** — occasionally still flaky.

**`StaticGenerator\Tests\Cache\EpochSweepIntegrationTest::testSnippetEditSweepsThePagesThatRenderIt`
— seen intermittently, undiagnosed.** Three times on 2026-08-05, on P8.5 and MariaDB
shards, never locally. It fails at whichever assertion comes first — `getSweptEpoch()`
null, or the regenerated file missing `snippet-v2` — which is the tell: the sweep the
handler was supposed to run did not take, so look at `HostCacheRefreshHandler` no-opping,
not at the assertion that caught it. Already **ruled out**: the static cache dir and the
epoch store, both per-worker via `PUSHWORD_TEST_VAR_DIR`. Start from the state manager's
own file and the debounce, and check the stale-cache section below first.

**`PageLockControllerTest::testPingAcquiresLockForEditor`** — not a flake at all. It fails
whenever *your own browser* has the admin edit screen for page 1 open, because the test
kernel and dev app share `packages/dev-app/var/page-locks/`. Check whether `lastPingAt` in
`page_1.json` advances a few seconds apart; a live session's lock has `username: "Admin"`
while a test-created one uses the email. Close the tab; do not delete the file.

**`AdminMediaPickerTest`** — rare Panther/chromedriver "Could not connect to server" on
port 95xx. Environmental, unrelated.

## Fixed flakes — a fresh failure is real

One line each; the post-mortems and already-walked dead ends are in
[references/resolved.md](references/resolved.md).

- `Flat\Tests\Command\ConsumePendingTest` — was a worker-shared
  `var/flat-sync/export-pending.json`; check first whether a new service reintroduced a
  hardcoded `%kernel.project_dir%/var`.
- `StaticGeneratorTest::testParallelGeneration*` ending `SQLSTATE[HY000]: General error:
  26 file is not a database` — the failing connection is Loupe's per-worker search
  index, not the dev-app DB; the cascade amplifier is fixed.
- A static-generation worker child dying `Worker N failed (exit 139: Segmentation
  violation)` — the workers' opcache file cache is gone; read past the assertion to the
  worker line, do not "fix" the assertion.
- `StaticGeneratorTest::testParallelWorkersPopulateAnOpcacheFileCache` — never a flake;
  it now probes what a child can actually cache and self-skips where CLI opcache is
  unusable.
- Variant races — a render throwing `public/media/xs/… not found` out of
  `image.html.twig` (`BlockExtensionTest`, `MediaCacheControllerTest`,
  `ImageOptimizerCommandTest`, `EpochSweepIntegrationTest`, `CacheClearCommandTest`) —
  derivatives are per worker now; check `PUSHWORD_TEST_MEDIA_CACHE_DIR` still reaches
  the container before reaching for `serial`.
- `LinkGraphCommandTest::testAnAllHostsScanLeavesEveryHostReadableOnItsOwn` — was the
  scan hitting `--limit` (**0 means 500, not "no limit"**) and deliberately writing no
  snapshot. Any new all-hosts scan test owes `--limit` too, and its first assertion is
  `assertStringNotContainsString('stopping scan', …)`.

## Failures that look like flakes but are stale caches

**`StaticGeneratorTest` 500 citing a validator/`Callback` method that no longer exists**,
or a static-render assertion behaving as if your `.twig` edit never happened. The static
generator renders through a separate **persistent debug=false kernel**, and in debug=false
Symfony tracks no source-file resources: the `system` PSR-6 pool (validator/serializer
metadata) is never invalidated, and Twig never recompiles. Isolated single-request tests
use the debug=true kernel and pass, so it reads as intermittent.

Fix: clear the shared caches, then re-run.

```bash
rm -rf "$(php -r 'echo sys_get_temp_dir();')/com.github.pushword.pushword/container-cache/test"
rm -rf packages/*/tests/var/*/cache
```

The cache dir comes from `App\Kernel::getCacheDir()`, **not** `packages/dev-app/var`.

**A broken-image comment where a `<picture>` belongs, and clearing the caches above
does not fix it.** The markdown fragment pool is deliberately built *next to*
`kernel.cache_dir` (`PushwordCoreExtension::registerMarkdownCachePool`) so deploys
cannot wipe it — which also means the two `rm -rf`s above miss it. One run against a
stale schema is enough to store the degraded render, and every later run serves it,
including in isolation. It masks the real exception too: `ImageRenderer` catches
`Throwable`, so instrumenting the catch shows nothing while the cache answers first.

```bash
rm -rf /tmp/com.github.pushword.pushword/container-cache/pushword-pools
```

When the real error turns out to be `no such column`, the DB cache is stale as well
(a peer's entity change, an interrupted rebuild): delete
`/tmp/com.github.pushword.pushword/test-db-cache` and re-run. That one failure
cascades to ~50 across media, gallery, static-generation and admin-frontend classes,
which reads as a broken tree and is one wrong `.sqlite`.

## Reproducing contention

Cross-worker races on a shared directory need real contention: loop
`vendor/bin/paratest --processes=8` about ten times. Do **not** use `--processes=auto` on
a high-core machine — it spreads files too thin to collide. For the duplicate-media
class of failure, `--processes=2` is the deterministic reproducer.

**To reproduce CI's layout rather than your machine's, `TEST_PROCESSES=4 composer test`.**
paratest distributes by class, so the worker count decides which classes share a process
— and that, not the machine, is what most of these failures depend on. A CI runner gives
`auto` 4; a 24-core desktop gives it 24, which is why a shard-dependent failure reproduces
nowhere locally. Six clean loops at 4 still proves little: several of the entries above
were only ever seen on CI.

## The systemic fix pattern

Once you have confirmed a flake is a shared-directory race, the fix (a `pw.*` container
parameter overridden per worker, or `#[Group('serial')]` where isolation is impossible)
lives in `.claude/rules/testing.md`. Version storage, the page-scan var dir and the media
derivative dir all use that mechanism. The last of those was documented for months as the
case where it *cannot* work — the compile-time constraint is real but applies to
`public_media_dir` (it is in route paths), not to the directory the files are written to,
which is now its own parameter. Check that distinction before accepting "cannot be
isolated" about anything else.
