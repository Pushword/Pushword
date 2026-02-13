## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

Pushword is a modular CMS built as a collection of Symfony bundles. This is a monorepo containing the core bundle and multiple extensions.

- **PHP** >= 8.4
- **Symfony** 8.0 (Framework principal)
- **Doctrine ORM** 3.0 (Gestion de base de données)
- **Twig** (Moteur de templates)
- **Node.js** + **Yarn** (Assets frontend)
- **Composer** (Gestion des dépendances PHP)

```
./
├── packages/
│ ├── skeleton/         # Debug and demo app (development environment)
│ ├── core/             # Core bundle with base entities and services
│ │ └── src/Entity/     # Core entities (Page, User, Media)
│ ├── docs/
│ │ └── content/        # Project documentation
│ └── [other-bundles]/  # Extension bundles (each with own src/ and tests/)
│ └── skeleton/         # Development/demo application
```

### Compatibility & Security

- Ensure compatibility with **Symfony** 8.0+ and **PHP** 8.4+ versions
- Follow secure coding practices to prevent XSS, CSRF, injections, auth bypasses, etc.
- **DB** : SQLite (via Doctrine) - No migrations, use `bin/console doctrine:schema:update --force`

### Coding Standards & Tooling

- Use **PHPUnit** for unit and functional testing via `composer test` or `composer test-filter ExampleTest` (never use directly `vendor/bin/phpunit`)
- Use php-cs-fixer`composer rector` to ensure consistent code style
- Use **PHPStan** `composer stan` for static analysis
- Use **CI** `composer test` to run all tests and checks automatically
- Use Tailwind CSS 4

## PHP Code

- Use modern PHP 8.4+ syntax and features
- Do not use deprecated features from PHP, Symfony, or Pushword
- Add type declarations for all properties, arguments, and return values
- Use `camelCase` for variables and method names
- Use `SCREAMING_SNAKE_CASE` for constants
- Use **fast returns** instead of nesting logic unnecessarily
- Use trailing commas in multi-line arrays and argument lists
- Use PHPDoc only when necessary (e.g. `@var Collection<ProductInterface>`)
- Group getter and setter methods for the same properties together
- Suffix interfaces with Interface, traits with Trait
- indent with 4 spaces

## Twig Templates

- indent with 2 spaces
- i18n : use camelCase for translatable text and keep them organized in `packages/<package>/translations/messages.<locale>.yaml` in alphabetical order.

### Entities

- **Page** - packages/core/src/Entity/Page.php
- **User** - packages/core/src/Entity/User.php
- **Media** - packages/core/src/Entity/Media.php

## Useful commands

You can run this command from skeleton folder `cd packages/skeleton` :

```bash
# List all commands
php bin/console list pushword

# Configuration
php bin/console debug:config pushword

# Routes
php bin/console debug:router

# Services
php bin/console debug:container
```

## Generate assets

```
composer assets
```

## Debugging

When a task impacts the admin UI or other frontend pieces, feel free to open the running site with the integrated browser to validate the behaviour and grab screenshots.

Use `symfony server:list` to see if a local server is already running, or start one with `composer dev`.

Default credentials are `admin@example.tld` / `p@ssword` (ROLE_SUPER_ADMIN). If that user fails, reset the demo via `composer reset-skeleton`.

## UI Testing with dev-browser

Use the `dev-browser` skill for automated UI checks and browser testing. This skill provides browser automation with persistent page state using Playwright.

```bash
# Start the dev-browser server first
/dev-browser
```

Example usage for checking the admin interface:

```typescript
import { connect, waitForPageLoad } from '@/client.js'

const client = await connect()
const page = await client.page('pushword-admin')
await page.setViewportSize({ width: 1280, height: 800 })

// Navigate to admin
await page.goto('https://127.0.0.1:8000/admin')
await waitForPageLoad(page)

// Login
await page.fill('input[name="_username"]', 'admin@example.tld')
await page.fill('input[name="_password"]', 'p@ssword')
await page.click('button[type="submit"]')
await waitForPageLoad(page)

// Take screenshot for verification
await page.screenshot({ path: 'tmp/admin-dashboard.png' })

await client.disconnect()
```

Use `getAISnapshot()` to discover page elements and `selectSnapshotRef()` to interact with them when you don't know the exact selectors.

## Design Guidelines

When working on UI, templates, CSS, or frontend-facing changes, consult `packages/core/DesignGuidelines.md` for design principles, visual standards and component patterns. This document targets Tailwind CSS and applies directly to public-facing templates. For the admin interface (which uses Bootstrap via EasyAdmin), apply only the underlying concepts (visual hierarchy, spacing rhythm, color roles, accessibility) — not the Tailwind-specific classes.

## Git Commits

- Never add AI footprint or signature in commit messages (no "Generated by", "Co-Authored-By: Claude", etc.)
- Keep commit messages concise and focused on the change

## Quality Gates

- Run linters `composer stan|rector` and tests `composer test-filter ExampleTest` or `composer test` and fix warnings; never leave broken builds.
- you must avoid to skip tests by using `self::markTestSkipped('...')` or `@group skip` in tests ➜ fix them !
- Clear cache after each modification `composer console cache:clear`
- Write comment and documentation in english only.

## Fix deprecations

```
# Symfony runtime deprecations (strict mode)
SYMFONY_DEPRECATIONS_HELPER='max[self]=0&max[direct]=0' composer test

# Container deprecations
php bin/console debug:container --deprecations
```
