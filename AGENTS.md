## Quick orientation

Read to bootstrap, in order:
1. This file — coding rules and project shape
2. `packages/docs/content/architecture.md` — bundle map and dev environment
3. `packages/docs/content/extensions.md` — what each extension does
4. `packages/core/src/Entity/Page.php` — main entity (8 traits in `SharedTrait/` and `PageTrait/`)
5. `packages/core/src/Entity/Media.php` — media entity
6. `packages/core/src/Event/PushwordEvents.php` — event constants

## Working principles

- Think before acting. Read existing files before writing code; don't re-read files already read.
- State assumptions; if uncertain or ambiguous, ask instead of picking silently.
- Push back when a simpler approach exists. Keep solutions simple and direct — minimum code, nothing speculative (no unrequested features, abstractions, flexibility, or error handling for impossible cases).
- Surgical changes: touch only what the request requires, match existing style, don't refactor working code. Remove orphans your change creates; flag (don't delete) pre-existing dead code.
- Prefer editing over rewriting whole files.
- Define success criteria and loop until verified ("add validation" → write tests for invalid inputs, then make them pass). State a brief plan for multi-step tasks.
- Test your code before declaring done.
- Be concise in output, thorough in reasoning. No sycophantic openers or closing fluff.

## Stack

Pushword is a modular CMS: a monorepo of Symfony bundles (core + extensions).

- **PHP** ≥ 8.4, **Symfony** 8.0, **Doctrine ORM** 3.0, **Twig**
- **Node.js** + **Yarn** (assets), **Composer** (PHP deps), **Tailwind CSS 4**
- **DB**: SQLite via Doctrine — no migrations, use `bin/console doctrine:schema:update --force`

```
packages/
├── skeleton/         # dev/demo app — run console commands from here
├── core/             # core bundle; src/Entity/ has Page, User, Media
├── docs/content/     # project documentation
└── [other-bundles]/  # extensions (each with own src/ and tests/)
```

### Compatibility & security

- Target Symfony 8.0+ / PHP 8.4+; no deprecated PHP/Symfony/Pushword features.
- Prevent XSS, CSRF, injection, auth bypass, etc.

## PHP code

- Modern PHP 8.4+ syntax; type declarations on all properties, args, returns.
- `camelCase` for variables/methods, `SCREAMING_SNAKE_CASE` for constants.
- Fast returns over nested logic. Trailing commas in multi-line arrays/arg lists.
- PHPDoc only when needed (e.g. `@var Collection<ProductInterface>`).
- Group getter/setter for the same property together.
- Suffix interfaces with `Interface`, traits with `Trait`. Indent 4 spaces.

## Twig templates

- Indent 2 spaces.
- i18n: camelCase keys, alphabetical, in `packages/<package>/translations/messages.<locale>.yaml`.

## Commands

From `packages/skeleton/`:

```bash
php bin/console list pushword          # all commands
php bin/console debug:config pushword  # config
php bin/console debug:router           # routes
php bin/console debug:container        # services
composer assets                        # build assets
composer dev                           # start server (symfony server:list to check)
composer reset-skeleton                # reset demo
```

Pushword CLI: `pw:flat:sync`, `pw:ai-index`, `pw:static`, `pw:media:normalize-filenames`, `pw:page-scan`, `pw:user:token {email}`.

## Quality gates

- Lint and test, fixing all warnings/notices: `composer stan`, `composer rector`, `composer test` (or `composer test-filter ExampleTest`). Never use `vendor/bin/phpunit` directly. Never leave a broken build.
- Never skip tests (`markTestSkipped`, `@group skip`) — fix them.
- Clear cache after each change: `composer console cache:clear`.
- Comments and docs in English only.

### Deprecations

```bash
SYMFONY_DEPRECATIONS_HELPER='max[self]=0&max[direct]=0' composer test  # runtime
php bin/console debug:container --deprecations                          # container
```

## Debugging UI

For admin UI or frontend changes, validate in the browser. Use the `dev-browser` skill (`/dev-browser`) for automated checks and screenshots — `getAISnapshot()` to discover elements, `selectSnapshotRef()` to interact.

Credentials: `admin@example.tld` / `p@ssword` (ROLE_SUPER_ADMIN); reset via `composer reset-skeleton`.

```typescript
import { connect, waitForPageLoad } from '@/client.js'

const client = await connect()
const page = await client.page('pushword-admin')
await page.goto('https://127.0.0.1:8000/admin')
await waitForPageLoad(page)
await page.fill('input[name="_username"]', 'admin@example.tld')
await page.fill('input[name="_password"]', 'p@ssword')
await page.click('button[type="submit"]')
await waitForPageLoad(page)
await page.screenshot({ path: 'tmp/admin-dashboard.png' })
await client.disconnect()
```

## Design

UI/templates/CSS: consult `packages/core/DesignGuidelines.md` (Tailwind, public templates). For admin (Bootstrap/EasyAdmin) apply only the underlying concepts — hierarchy, spacing, color roles, accessibility — not Tailwind classes.

## Git commits

- No AI footprint/signature (no "Generated by", "Co-Authored-By: Claude").
- Concise, focused on the change.

## Docs

- `packages/docs/content/` — one `.md` per topic; `extension/` — per-bundle feature docs.
- `packages/core/DesignGuidelines.md` — UI/design principles.

## For AI agents on a downstream Pushword site

Read from `vendor/pushword/docs/content/`: `architecture.md`, `extensions.md`, `media-api.md`, `ai-index.md`. Entity source in `vendor/pushword/core/src/Entity/` (docblocks summarize composition).

**Multi-locale**: the `host` field drives multi-site/locale — each locale is a separate host (e.g. `altimood.com`, `us.altimood.com`). Pages link across locales via the `translations` custom property; localize slugs per language.

A downstream CLAUDE.md should cover: project purpose/stack, hosts/locales table, common commands, deployment workflow, editorial rules (in `.rules/` or `docs/`), framework-unenforced invariants, and a pointer to `vendor/pushword/docs/content/`.
