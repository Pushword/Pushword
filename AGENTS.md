## Quick orientation

Read to bootstrap, in order:
1. This file — coding rules and project shape
2. `packages/docs/content/architecture.md` — bundle map and dev environment
3. `packages/docs/content/extensions.md` — what each extension does
4. `packages/core/src/Entity/Page.php` — main entity (10 traits in `SharedTrait/` and `PageTrait/`)
5. `packages/core/src/Entity/Media.php` — media entity
6. `packages/core/src/Event/PushwordEvents.php` — event constants

## Working principles

- Nothing speculative: no unrequested features, abstractions, flexibility, or error handling for impossible cases.
- Remove orphans your change creates; flag (don't delete) pre-existing dead code.

## Stack

Pushword is a modular CMS: a monorepo of Symfony bundles (core + extensions).

- **DB**: SQLite by default; PostgreSQL and MariaDB via Doctrine — no migrations, use `bin/console doctrine:schema:update --force`
- No deprecated PHP/Symfony/Pushword features.

## Conventions

Match the surrounding code; php-cs-fixer and Rector settle the rest. Two things they don't:

- Group getter/setter for the same property together.
- Twig i18n: camelCase keys, alphabetical, in `packages/<package>/translations/messages.<locale>.yaml`.

## Commands

`composer` scripts run from the repo root; `bin/console` from `packages/dev-app/`.

```bash
composer console list pw               # all commands (or bin/console from packages/dev-app/)
composer assets                        # build assets
composer dev                           # start server (symfony server:list to check)
composer reset-dev-app                # reset demo
```

**Agent-optimized output**: some commands emit one compact JSON line when run by an AI agent (auto-detected) instead of progress/colors/timing. Add it to a command with `Pushword\Core\Command\AgentOutputTrait` + a `--format` option (auto|agent|text); gate every human write behind `if (! $this->agentMode)`. Tests asserting human output must pass `'--format' => 'text'`. `packages/docs/content/agent-output.md` lists which commands support it — update that list, not this file.

## Quality gates

- Lint and test, fixing all warnings/notices: `composer stan`, `composer rector`, `composer test` (or `composer test-filter ExampleTest`). Never use `vendor/bin/phpunit` directly. Never leave a broken build.
- Never skip tests (`markTestSkipped`, `@group skip`) — fix them.
- Clear cache after each change: `composer console cache:clear`.
- Comments and docs in English only.

### Mandatory post-change review

For every task that creates, modifies, or deletes code, tests, configuration,
templates, styles, assets, or documentation:

1. After completing the implementation and before sending the final response, invoke
   `$is-it-well-tested`.
2. Apply every no-brainer test it identifies. If it requires confirmation for a
   non-obvious test, ask the user and do not present the task as complete.
3. Then invoke `$code-simplifier`, scoped strictly to files changed during the current
   conversation.
4. If either skill modifies files, rerun the relevant tests and quality gates, including
   `composer console cache:clear`.
5. Inspect the final diff and status, then commit only the files changed for the current
   task. Add new files with a scoped `git add <path>` immediately before committing, and
   use `git commit --only -m "type(scope): subject" -- <paths>`.
6. Verify that the commit exists and that no file belonging to the current task remains
   uncommitted. Never include unrelated user or peer changes in the commit.
7. Do not send the final response until both skills, the relevant checks, and the scoped
   commit have completed successfully.
8. End the final response with exactly the following status, replacing `<commit-sha>`
   with the actual commit hash:
   `Post-change review: is-it-well-tested complete; code-simplifier complete; committed <commit-sha>`

For a read-only analysis, explanation, or status request, do not run this review. Changes
made by these two skills do not recursively trigger another review; rerun only the
relevant verification commands. If `is-it-well-tested` requires user input, end the
response with exactly:
`Post-change review: awaiting user confirmation from is-it-well-tested`

### Deprecations

```bash
SYMFONY_DEPRECATIONS_HELPER='max[self]=0&max[direct]=0' composer test  # runtime
php bin/console debug:container --deprecations                          # container
```

## Debugging UI

For admin UI or frontend changes, validate in the browser. Use the `dev-browser` skill (`/dev-browser`) for automated checks and screenshots — `page.snapshotForAI()` to discover elements, then act on them by node id (or with plain Playwright selectors when they are known).

Credentials: `admin@example.tld` / `p@ssword` (ROLE_SUPER_ADMIN); reset via `composer reset-dev-app`.
Admin login script: `.claude/skills/ui-debug/SKILL.md`.

## Design

UI/templates/CSS: consult `packages/core/DesignGuidelines.md` (Tailwind, public templates). For admin (Bootstrap/EasyAdmin) apply only the underlying concepts — hierarchy, spacing, color roles, accessibility — not Tailwind classes.

## Git commits

- No AI footprint/signature (no "Generated by", "Co-Authored-By: Claude").
- Concise, focused on the change.
- The working tree AND its single git index are shared with parallel agents. Commit scoped:
  `git commit --only -m "type(scope): subject" -- <paths>` — new files need a scoped `git add <path>` first.
- Never bare `git commit`, `commit -a`, or tree-wide `git add` (`-A`/`-u`/`.`): they sweep whatever a peer has staged.
- Staging is not a save point — never leave files staged; stage only right before your own `--only` commit.
- Never stash, hard-reset, `checkout`/`restore .`, or delete/unstage files you didn't create.
- Whole-tree commits are a human-terminal operation — stop and ask Robin.

## Docs

- `packages/docs/content/` — one `.md` per topic; `extension/` — per-bundle feature docs.
- `packages/core/DesignGuidelines.md` — UI/design principles.
- `packages/docs/content/upgrade/` — one note per release, indexed by `upgrade.md`.
  Does your change ask something of a site that upgrades — a command to run, a config
  key, a template to copy, a behaviour that changed under an unchanged call? Then write
  it into `upgrade/next-release.md` in the same commit; the format is in that file.
  `.scripts/release` renames it to `rc<N>.md` and adds its index row at the tag, so
  never create `rc<N>.md` or edit the table by hand.

## For AI agents on a downstream Pushword site

See `packages/docs/CLAUDE.md` — the file that ships as `vendor/pushword/docs/CLAUDE.md`.
