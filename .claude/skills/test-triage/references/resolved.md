# Resolved flakes — post-mortems

The one-line verdicts live in SKILL.md; this file holds the full histories. Read the
matching entry before re-investigating a recurrence — the dead ends are recorded here
precisely so they are not walked twice.

## `Flat\Tests\Command\ConsumePendingTest`

Four flat services hardcoded `%kernel.project_dir%/var`, so every worker shared one
`var/flat-sync/export-pending.json`: any test touching a page wrote the flag this one
then read. They take `%pw.var_dir%` now. On a fresh failure, check whether a new service
reintroduced the hardcoded path.

## Loupe index corruption — `SQLSTATE[HY000]: General error: 26 file is not a database`

`StaticGeneratorTest::testParallelGeneration*` ended with that line. The connection that
fails is Loupe's, opened from `IndexManager::getLoupe()` under
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
was never caught in the act — it did not reproduce in 4 full local batch pairs; see the
segfault entry below before treating a recurrence as a new problem.

It once hit both PHP 8.5 jobs while all 8.4 and MariaDB jobs passed, which *looks* like a
version regression and is not — one flaked shard cancels its fail-fast siblings (triage
step 4 in SKILL.md).

Locally the same class used to throw `Unable to guess
"…/public/media/md/2.jpg.<n>.<hash>.tmp" file type` — an image-optimizer temp file caught
mid-write in the then-shared `public/media` dir. Derivatives are per worker since
2026-08-05, so a fresh occurrence is real.

## Worker child segfault — `Worker N failed (exit 139: Segmentation violation): no error output`

Fixed 2026-08-05 by dropping the workers' opcache file cache. It took down whichever
`StaticGeneratorTest` case was building at `assertStringContainsString('success', …)`:
the run listed every page it handled, then died with no summary. Nothing about those
tests was wrong — if it returns, read past the assertion to the worker line and do not
"fix" the assertion.

The cause was the flags `StaticAppGenerator` spawned each child with:
`-d opcache.enable=1 -d opcache.enable_cli=1 -d opcache.file_cache=<dir>`. First seen on
alt-php 8.4.3 and answered by giving each worker its own cache directory; it came back on
stock 8.4 *and* 8.5 with the directories already unshared, and on the serial batch where
only one build runs at a time — so sharing was never the whole of it and the flags
themselves had to go. It was a production crash CI happened to catch: a real
`pw:static --workers=N` ran with the same flags.

Two dead ends recorded so they are not re-run: it hit `P8.4 - N25` twice and `P8.5 - N25`
once, which looks like a Node correlation and is not one; and it never reproduced locally,
at `TEST_PROCESSES=4` or otherwise. Peak memory was 218 MB, so never the memory limit.

**Worth connecting:** the Loupe entry above says what damaged the index "was never caught
in the act", and describes exactly a writer killed mid-checkpoint with `synchronous =
OFF`. A segfaulting worker child is such a writer. Before treating these as two problems,
check whether a run showing the corruption also shows an exit 139.

## `StaticGeneratorTest::testParallelWorkersPopulateAnOpcacheFileCache` — was never a flake

It failed on the MariaDB job and only there, every run, because that job is the only one
setup-php gives a usable CLI opcache (`coverage: pcov` shadows it on the others, so the
test early-returned and passed trivially). Adding `opcache.enable=1` next to
`opcache.enable_cli=1` in the worker spawn was the obvious cure and did not work — the
job stayed red. A child on that runner writes nothing to an `opcache.file_cache` dir even
with every flag set, so the capability the test asserted was never observable there. The
test now spawns its own probe child and returns early when that child caches nothing,
which is the only honest precondition: `extension_loaded()` in the test process says
nothing about what a child can do. A local CLI with opcache still runs the assertion for
real, so the test keeps its teeth. A fresh failure means a probe child *did* file-cache
and the workers did not.

## Variant races — fixed at the root 2026-08-05

The five classes that were in `serial` for them are parallel again (`BlockExtensionTest`,
`MediaCacheControllerTest`, `ImageOptimizerCommandTest`, `EpochSweepIntegrationTest`,
`CacheClearCommandTest`). The symptom was a render *throwing* on a variant missing at
that instant (`public/media/xs/piedweb-logo.png not found`, out of `image.html.twig`)
because a peer was generating or optimising the same file. Derivatives now go to
`pw.media_cache_dir`, per worker. If one of these flakes again, check the isolation is
still wired (`PUSHWORD_TEST_MEDIA_CACHE_DIR` reaching the container) before reaching for
`serial`.

## `LinkGraphCommandTest::testAnAllHostsScanLeavesEveryHostReadableOnItsOwn`

It failed `null is not null` on line 287 a few runs in ten, which reads as "the snapshot
was not written" and was nothing of the sort. `pw:page-scan` abandons the loop past
`--limit` findings (**0 means 500, not "no limit", whatever the option description
says**) and then deliberately writes *no* snapshot, since the graph would be missing the
edges of every page it never reached. The whole corpus scanned at once sits within a
couple of findings of that ceiling — 502 on the dev-app at the time — so which side of
500 a run landed on decided the test. It passes `--limit` now and asserts the scan did
not stop short. Any new test that scans every host at once owes the same, and the
assertion to write first is `assertStringNotContainsString('stopping scan', …)` — not
one about the snapshot.
