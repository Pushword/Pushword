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

## Homepage translations "vanish" mid-worker — a destructive restore, an ambiguous pick, and a form that unpublishes

The family: `PageRepositoryTest::testPreloadTranslationsInitializesEveryCollectionAtOnce`
("array does not contain 'fr'", later "null is not Page"), a sitemap run missing the
homepage's hreflang alternates, `LinkGraphCommandTest::testARedirectionIsNotAGraphNode`
missing `localhost.dev/homepage`, `PageExtensionPagesListTest` failing all seven tests
at once (`pages_list('slug:homepage')` rendering the same empty list as a bogus slug) —
roughly two full local runs in five, never in isolation, never in the pairs that were
tried. It took **three** fixes, each exposed by the survivor of the previous one.

**Mechanism 1 — real link loss.** `PageUpdateNotifierTest::testRun` needed a page set
with no recent timestamps, so it saved seven scalar fields of every `localhost.dev`
page, **deleted them all** (nulling `parentPage`/`variantOf` first for MariaDB's FKs),
ran its assertions, and recreated the pages from the scalars. The self-referencing
`translations` ManyToMany join rows die with the delete (`ON DELETE CASCADE` on
join-table FKs is Doctrine's default), and the restore never rebuilt them — nor
`mainImage`, `parentPage`, `variantOf`, or the media-usage rows. Every worker that ran
the class ended poisoned, deterministically. The fix: backdate `createdAt`/`updatedAt`
in place (`skipAutoTimestamp = true`, or `PageListener::preUpdate` re-touches them) and
restore the saved timestamps after — no delete, nothing else to restore.

**Mechanism 2 — healthy DB, wrong homepage.** Two fixture hosts own a `homepage` slug,
and only `localhost.dev`'s carries translations. Several flat tests legitimately
delete-and-reimport the whole host (`PageSyncTest`'s force-reset among them) — links
correctly restored, but **rows renumbered**: the localhost.dev homepage moves from id 1
to id ~250, so the `admin-block-editor.test` homepage (id 6, no translations) now comes
first in `getPublishedPages('')` (`''` means *no host filter*). The victim's
`array_find(…, slug === 'homepage')` then picks the wrong host's homepage and reads its
genuinely empty collection. Fix: guard the find with `host === 'localhost.dev'`. The
same unguarded pattern — `findOneBy(['slug' => 'homepage'])` — sits in a dozen tests
that only need *a* page; add the host guard the moment one starts asserting on
translations, hreflang, or anything host-specific.

**Mechanism 3 — the homepage is there, but unpublished.** With 1 and 2 fixed the victim
still failed, now as `null`, with every worker DB autopsying healthy and *un-renumbered*
(homepage id 1). A failure-time dump added to the victim showed the row present with
`published_at = NULL` — and `getPublishedPages` filters `publishedAt IS NOT NULL`.
`PageEditNoopSaveTest` drives the fixtures' homepage through the real EasyAdmin edit
form; `testThePageCanStillBeUnpublishedFromTheForm` submits `Page[publishedAt] = ''`
and the class restored nothing. It now snapshots the columns it touches on first
`getPageId()` and restores them by SQL in `tearDown()` (SQL so the restoration cuts no
version, queues no export, bumps no `updatedAt`).

Dead ends, so they are not walked twice:

- **Pairing the victim with suspects via `composer test-filter 'Suspect|Victim'` proves
  nothing when the suspect sorts after the victim.** Filtered runs execute in suite
  order — the `packages/*/tests/` glob — so `core`'s victim ran before `flat`'s and
  `page-scanner`'s suspects in every pair tried, and the poisoner
  (`page-update-notifier`, after both) was never even on the suspect list. To exercise
  poisoner-before-victim, write a scratch phpunit XML listing the two `<file>`s in that
  order; with it the "flake" reproduced in 1.3s, every time.
- **Cross-worker theories are wrong by construction here** — each worker owns its DB, so
  the poisoner always shares the victim's worker, sequentially. Look for a class, not a
  race.
- **Worker DBs do not survive `composer test`** (`trap cleanup EXIT` in `.scripts/test`),
  so a post-mortem needs the parallel batch run directly with a private
  `TEST_RUN_ID` (`vendor/bin/paratest --processes=auto …`, same flags as the script).
  The autopsy that cracked it: query every leftover `<runid>-w*/test.db` for the
  homepage's translation links — a worker can end poisoned even in a green run, so one
  run usually convicts — then read that worker's `version_log` table, which timestamps
  every create/update and turns leftover uniqid slugs into a class-by-class timeline.
- A worker DB whose fixture pages carry high ids **with** their translations intact was
  first dismissed as "a legit flat restore, not the poisoner's trail" — that dismissal
  was the expensive mistake. The restore is legit, but the renumbering it leaves behind
  is exactly what arms mechanism 2. When the victim fails and every worker DB autopsies
  healthy, don't conclude "in-process bug in the victim" first — ask what *ordering*
  the healthy-looking DB no longer guarantees.
- **Grepping for direct mutators finds only direct mutators.** The suspect lists built
  from `setTranslations|removeTranslation|publishedAt = null` could never contain
  `PageEditNoopSaveTest`: it mutates through a submitted admin form. The
  `version_log.editor` column is the way in — fixture pages are written by fixtures and
  sync imports (editor empty), so an *authenticated* editor on a fixture page row names
  an admin/API test, and the timestamps place it between its neighbours.
- **When theories multiply, make the victim dump its own crime scene.** The decisive
  move for mechanism 3 was a failure-time dump inside the test: connected DB path, file
  size, raw `SELECT COUNT(*)`, the raw homepage row, and the filtered view's counts.
  One wild reproduction then separated "row present but publishedAt NULL" from three
  plausible-sounding theories walked first and exonerated: a background
  `pw:flat:sync --consume-pending` child (never runs — messenger-inline, then the
  debounce skips), torn reads from `copy()`-based pristine restores racing lingering
  `pw:image:*`/`pw:static:worker` children (children are real — the ps-sampler counted
  hundreds — but no torn state was ever observed), and cross-run interference from a
  parallel `composer test` (per-run per-worker DBs make it impossible; reproductions
  ran alone).

The pristine-restore `copy()` over a live SQLite file with detached children still
holding write connections remains a *plausible* future flake source even though it was
not this family's cause — an atomic `copy`-to-temp + `rename()` is the cheap hardening
if something of that shape ever shows.
