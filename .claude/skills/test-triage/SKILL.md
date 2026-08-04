---
name: test-triage
description: Decide whether a failing Pushword test is a real regression or a known pre-existing flake. Use when composer test, composer test-mariadb, or CI goes red and you need to know if your change caused it.
---

# Is this failure mine?

The suite has a cluster of pre-existing flakes that **hop between parallel shards**, so
re-running surfaces a different one each time and chasing green by re-running does not
converge. Check the failing test against this dossier before investigating your change.

## Triage order

1. Is the failing test named below? If yes, it is almost certainly pre-existing noise.
2. Re-run that test **in isolation**. Passing in isolation but failing under load means
   pollution, not a portability bug.
3. Before blaming a cross-worker race, look for the *class* that poisons it and re-run
   just that pair: `composer test-filter 'ThatClass|FailingClass'`. Workers get their own
   DB, media, var and flat content dir (`$testBaseDir` carries `w<token>`), so a fixture
   another class destroyed and never restored is the likelier cause — and it reads
   exactly like a race, because paratest distributes by class and only some layouts put
   the two together. That is what
   `LinkedDocsScannerTest::testRedirectionIsReportedOnEveryPageLinkingIt` turned out to
   be: `Flat\Tests\PageSyncTest` imported a redirection.csv without the fixtures' own
   rows, and `import()` deletes what the csv omits.
4. Confirm the tests *your* change touches pass deterministically — repeat-run locally,
   then `composer test-mariadb`.

## Known flakes

**`Flat\Tests\Command\ConsumePendingTest` — fixed, no longer a flake.** Four flat
services hardcoded `%kernel.project_dir%/var`, so every worker shared one
`var/flat-sync/export-pending.json`: any test touching a page wrote the flag this one
then read. They take `%pw.var_dir%` now. Treat a fresh failure here as real, and check
whether a new service reintroduced the hardcoded path.

**`StaticGenerator\Tests\StaticGeneratorTest::testParallelGeneration*`** — output ends
`SQLSTATE[HY000]: General error: 26 file is not a database`. **The amplifier is fixed;
treat a fresh occurrence as real.** Read the stack before assuming it is the dev-app DB:
the connection that fails is Loupe's, opened from `IndexManager::getLoupe()` under
`StaticSearchSubscriber::onPostGenerate` — the file is the per-worker search index,
`%pw.var_dir%/search/<host>/loupe.db`, not `test.db`.

Why one damaged file used to redden a whole run: Loupe sets `synchronous = OFF`
(`LoupeFactory::optimizeSQLiteConnection`), so a writer killed mid-checkpoint leaves
`loupe.db` with a torn header — and SQLITE_NOTADB is **permanent**, so every later
process that opened that index failed too. Since the index is built on post-generate,
each failure took the whole build down with it. That is why the first casualty was
whichever static test ran first (`CacheClearCommandTest::testCacheClearWarmsFiles` in one
run) and everything after it fell over the same way, and why the serial batch was the
victim: it reuses `TEST_TOKEN=1`, i.e. parallel worker 1's var dir, because CI runs both
batches under one `TEST_RUN_ID`. `IndexManager` now resets an unreadable index instead of
propagating, so the damage no longer cascades. What *damaged* the file in the first place
was never caught in the act — it did not reproduce in 4 full local batch pairs.

It once hit both PHP 8.5 jobs while all 8.4 and MariaDB jobs passed, which *looks* like a
version regression and is not. Note the matrix is fail-fast, so one flaked shard cancels
its siblings and the whole run reads red — check `conclusion` on the siblings via
`gh api repos/:owner/:repo/actions/jobs/<id>` before believing four jobs failed.

Locally the same class throws `Unable to guess
"…/public/media/md/2.jpg.<n>.<hash>.tmp" file type` — an image-optimizer temp file caught
mid-write in the shared `public/media` dir. That one re-runs green and passes in isolation.

**`StaticGeneratorTest::testParallelWorkersPopulateAnOpcacheFileCache` — was never a
flake.** It failed on the MariaDB job and only there, every run, because that job is the
only one setup-php gives a usable CLI opcache (`coverage: pcov` shadows it on the others,
so the test early-returned and passed trivially). Adding `opcache.enable=1` next to
`opcache.enable_cli=1` in the worker spawn was the obvious cure and did not work — the job
stayed red. A child on that runner writes nothing to an `opcache.file_cache` dir even with
every flag set, so the capability the test asserted was never observable there. The test
now spawns its own probe child and returns early when that child caches nothing, which is
the only honest precondition: `extension_loaded()` in the test process says nothing about
what a child can do. A local CLI with opcache still runs the assertion for real, so the
test keeps its teeth. Treat a fresh failure here as real — it means a probe child *did*
file-cache and the workers did not.

**`PageScanner\Tests\Api\PageScanApiControllerTest`** — occasionally still flaky.

**`PageLockControllerTest::testPingAcquiresLockForEditor`** — not a flake at all. It fails
whenever *your own browser* has the admin edit screen for page 1 open, because the test
kernel and dev app share `packages/dev-app/var/page-locks/`. Check whether `lastPingAt` in
`page_1.json` advances a few seconds apart; a live session's lock has `username: "Admin"`
while a test-created one uses the email. Close the tab; do not delete the file.

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

Shared-`public/media` races need real contention: loop
`vendor/bin/paratest --processes=8` about ten times. Do **not** use `--processes=auto` on
a high-core machine — it spreads files too thin to collide. For the duplicate-media
class of failure, `--processes=2` is the deterministic reproducer.

## The systemic fix pattern

Once you have confirmed a flake is a shared-directory race, the fix (a `pw.*` container
parameter overridden per worker, or `#[Group('serial')]` where isolation is impossible)
lives in `.claude/rules/testing.md`. Version storage and the page-scan var dir already use
that mechanism; `public/media/{filter}/` is the case where it cannot work.

## Environmental, unrelated

`AdminMediaPickerTest` has a rare Panther/chromedriver "Could not connect to server"
failure on port 95xx. Environmental.
