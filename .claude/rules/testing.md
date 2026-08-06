---
paths:
  - "packages/*/tests/**"
  - ".scripts/test*"
  - ".github/workflows/*test*"
  - "packages/core/tests/bootstrap.php"
---

# Testing invariants

The suite runs under ParaTest with **per-worker SQLite DBs**. Sequential runs
(`--no-parallel`) share one DB and surface pre-existing isolation failures that are not
real bugs.

- **A failing test is not automatically your fault.** Several known flakes hop between
  shards. Before investigating, invoke the `test-triage` skill — it holds the current
  dossier of known-flaky tests and their signatures.
- **Shared-`var/` state is the recurring flake source.** Any service writing under
  `var/` must route the path through a `pw.*` container parameter (default
  `%kernel.project_dir%/var`) and be overridden per worker in
  `packages/dev-app/config/packages/test/pushword.php` from `%env(PUSHWORD_TEST_VAR_DIR)%`.
  Prefer this over runtime `getenv()` in service constructors — it keeps test concerns
  out of production code.
- **Derivatives are isolated per worker too, since 2026-08-05.** They no longer live at
  `public_dir`/`public_media_dir` (the second of which *is* compile-time: it is in route
  paths) but under `pw.media_cache_dir`, overridden from `%env(PUSHWORD_TEST_MEDIA_CACHE_DIR)%`.
  A variant race between workers is therefore no longer a plausible diagnosis, and the
  five classes that were in `#[Group('serial')]` for it are back in the parallel batch.
  The browser path is unchanged, so a worker that finds no derivative on disk still gets
  one from the media-cache route.
- **`#[Group('serial')]` is still the answer for a genuinely unisolatable resource**, and
  it runs after the parallel batch. Wire changes in **both**
  `.github/workflows/run-tests.yml` and `.scripts/test` — CI calls paratest directly and
  does not use the script.
- **A background console task runs in the env that dispatched it** — `BackgroundCommand::pinEnvironment()`
  appends `--env`, because a child inherits `APP_ENV` and PHPUnit does not export it. Without
  it those children ran in dev, against the dev database and the dev app's directories, and
  the test asserting on them was reading someone else's state.
- **The suite is CPU-bound, not schedule-bound.** The parallel batch burns ~450
  thread-seconds on 24 threads at ~75% occupancy, so its floor is ~19s and the only real
  win is *less work*, never better packing. Two levers measured and rejected:
  `--order-by=duration-descending` (PHPUnit compares a suite's `sortId()` against the
  result cache, which only holds `Class::method` keys — so every class ties, and it
  reorders only methods *within* a class, breaking tests that carry an intra-class order
  dependency) and `--functional` (37s, 16GB peak RSS, and splitting a Panther class across
  processes collides on its web-server port). What to look for instead is a test that fans
  out to subprocesses: `StaticGeneratorTest` ran `pw:static` with auto workers, i.e. one
  kernel-booting subprocess per published page, and cost 116 CPU-seconds — a fifth of the
  whole suite — until those runs asked for `--workers 1`.
- **A CLI `--group`/`--exclude-group` discards the whole XML `<groups>` block** — PHPUnit
  replaces that config rather than merging it. `phpunit.xml.dist` excludes `benchmark`,
  so any batch passing a group flag must repeat `--exclude-group=benchmark` or the
  benchmarks run. Missing it on the parallel batch cost `composer test` ~16s (the
  59s `RepositoryBenchmarkTest` plus `StaticGeneratorBenchmarkTest`) while CI, which
  passes the flag, skipped them.
- **A *new* config file does not invalidate the test container cache.** The test kernel
  keeps a persistent shared container at `/tmp/com.github.pushword.pushword/container-cache/test`
  so workers reuse one compile. Editing a file it already tracks invalidates it correctly;
  *adding* one to a glob-imported dir (e.g. a new `config/packages/test/*.php`) does not —
  the config silently does not apply, and `debug:config` still reports the new value, so
  it looks applied. `composer console cache:clear` only clears **dev**. After adding a
  config file, `rm -rf /tmp/com.github.pushword.pushword/container-cache/test`.
- **A failed DB build gets cached and poisons every later run.** The doctrine commands in
  `computeDbCacheHash`'s miss path run through `Application::setAutoExit(false)`, so a
  failure returns a code nobody reads and the empty `test.db` is copied to the cache
  anyway. Every later run then hits that 0-byte `.sqlite` and fails with
  `no such table: user` across the whole suite. If you see that, look for a 0-byte file:
  `find /tmp/com.github.pushword.pushword/test-db-cache -name '*.sqlite' -size 0 -delete`.
  The usual trigger is a container that does not compile at the moment a rebuild happens.
- **Agent output breaks status-quo tests.** The suite itself runs with `CLAUDECODE` set,
  so commands using `AgentOutputTrait` auto-switch to JSON. Any test asserting human
  output must pass `'--format' => 'text'`; add a `'--format' => 'agent'` test for the
  JSON path.
- **New entities need `computeDbCacheHash`.** A package's `src/Entity` directory missing
  from `computeDbCacheHash` in `packages/core/tests/bootstrap.php` means schema changes
  silently reuse a stale cached test DB.
- **Never run `vendor/bin/phpunit` without `TEST_RUN_ID`.** With no run id the bootstrap
  drops its isolation: it writes `test.db` into the shared `/tmp/.../tests/` dir (never
  cleaned) and mirrors media into the real `packages/dev-app/media`. If such a run is the
  one that misses the DB cache, it builds the pristine copy on top of the leftover
  `test.db` — the fixture purge is `DELETE FROM`, which does not reset SQLite's
  `sqlite_sequence`, so entity ids come out shifted and every later run inherits it.
  Tests hard-coding ids (`AdminTest` wants user `id = 1`) then fail in isolation and look
  like real regressions. Cure: delete the cached `.sqlite` under
  `/tmp/com.github.pushword.pushword/test-db-cache/` plus the stray
  `/tmp/com.github.pushword.pushword/tests/test.db`, then re-run through `composer test`.
- **`assertEmailCount()`'s second argument is `$transport`, not the failure message**
  (`assertEmailCount(int $count, ?string $transport = null, string $message = '')`).
  Passing a message there filters on a transport that does not exist and always reports
  "0 sent". Also: one `send()` logs two `MessageEvent`s (queued + sent); `assertEmailCount`
  counts only the non-queued one, while `getMailerMessages()` returns both.
- **Reading a `StreamedResponse` body** in a `WebTestCase`: use
  `$client->getInternalResponse()->getContent()`. `$client->getResponse()->getContent()`
  returns `false` — the client already consumed the stream.
- **Status 200 is not an assertion.** A wrong Twig block name renders nothing and still
  returns 200. Assert on the rendered HTML.
- **SQLite enforces foreign keys too, since 2026-08-06** (`SqliteConnectionPragmas`), so
  an FK-ordering bug now fails the default suite rather than only `composer test-mariadb`.
  The pragma is lifted for `doctrine:schema:` commands only — SQLite rebuilds a table by
  dropping it, which would cascade every child row away. When triaging a MariaDB failure,
  re-run the test in isolation first; if it passes, it is parallel pollution, not a
  portability bug.
- **`interface_exists()` guards can never be exercised in-repo.** Every class is PSR-4
  autoloadable regardless of which bundle is registered, and the dev-app boots them all,
  so those guards are always true under PHPUnit. Only the registration-based branch
  (`->nullOnInvalid()`) is reachable in-process. Real coverage comes from
  `composer test-partial-install`, which does isolated composer installs of curated
  subsets via path repos — CI only, not part of `composer test`.
- **PHPStan needs `cache:warmup --env=dev`**, never `cache:clear` — phpstan-symfony reads
  the debug container XML, which only a container *compile* writes.
- **A new package must be added to `phpstan.dist.neon` paths** (both `src` and `tests`) —
  `composer stan` silently skips anything not listed.
