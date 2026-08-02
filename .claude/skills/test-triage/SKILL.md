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

**`Flat\Tests\Command\ConsumePendingTest::testConsumePendingReadsFlagAndRunsExport`** —
missing `var/flat-sync/localhost_dev_lock.json`; a shared `var/flat-sync` race. Not fixed.

**`StaticGenerator\Tests\StaticGeneratorTest::testParallelGeneration*`** — in CI, output
ends `SQLSTATE[HY000]: General error: 26 file is not a database`: parallel-worker child
processes racing the dev-app SQLite file. This has hit both PHP 8.5 jobs at once while
all 8.4 and MariaDB jobs passed, which *looks* like a version regression and is not — a
plain `gh run rerun --failed` goes green with no code change. Locally the same class
throws `Unable to guess "…/public/media/md/2.jpg.<n>.<hash>.tmp" file type` — an
image-optimizer temp file caught mid-write in the shared `public/media` dir. Both re-run
green and pass in isolation.

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
