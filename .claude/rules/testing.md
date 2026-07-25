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
- **`public/media/{filter}/` cannot be isolated per worker** (`public_dir` is a
  compile-time param, container shared across workers). A test that mutates or reads a
  shared variant path and flakes belongs in `#[Group('serial')]`, which runs after the
  parallel batch. Wire changes in **both** `.github/workflows/run-tests.yml` and
  `.scripts/test` — CI calls paratest directly and does not use the script.
- **Agent output breaks status-quo tests.** The suite itself runs with `CLAUDECODE` set,
  so commands using `AgentOutputTrait` auto-switch to JSON. Any test asserting human
  output must pass `'--format' => 'text'`; add a `'--format' => 'agent'` test for the
  JSON path.
- **New entities need `computeDbCacheHash`.** A package's `src/Entity` directory missing
  from `computeDbCacheHash` in `packages/core/tests/bootstrap.php` means schema changes
  silently reuse a stale cached test DB.
- **Reading a `StreamedResponse` body** in a `WebTestCase`: use
  `$client->getInternalResponse()->getContent()`. `$client->getResponse()->getContent()`
  returns `false` — the client already consumed the stream.
- **Status 200 is not an assertion.** A wrong Twig block name renders nothing and still
  returns 200. Assert on the rendered HTML.
- **MariaDB** (`composer test-mariadb`) is the only way FK-ordering bugs surface — SQLite
  does not enforce foreign keys. When triaging a MariaDB failure, re-run the test in
  isolation first; if it passes, it is parallel pollution, not a portability bug.
- **`interface_exists()` guards can never be exercised in-repo.** Every class is PSR-4
  autoloadable regardless of which bundle is registered, and the dev-app boots them all,
  so those guards are always true under PHPUnit. Only the registration-based branch
  (`->nullOnInvalid()`) is reachable in-process. Real coverage comes from
  `composer test-partial-install`, which does isolated composer installs of curated
  subsets via path repos — CI only, not part of `composer test`.
- **PHPStan needs `cache:warmup --env=dev`**, never `cache:clear` — phpstan-symfony reads
  the debug container XML, which only a container *compile* writes.
- The **quiz** package is absent from `phpstan.dist.neon` paths; `composer stan` skips it.
  Analyse it explicitly.
