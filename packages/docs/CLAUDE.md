## Pushword — AI agent reference

This file ships to your project via `vendor/pushword/docs/CLAUDE.md`. Reference it from your project's CLAUDE.md instead of duplicating Pushword knowledge.

### Coding principles

1. **Think before coding**: State assumptions. Present alternatives — don't pick silently. Push back if simpler exists.
2. **Simplicity first**: Minimum code. No speculative abstractions.
3. **Surgical changes**: Touch only what you must. Match existing style. Remove only orphans your changes created.
4. **Goal-driven execution**: Write a failing test first when possible. State a brief plan with verification steps.

### Content structure

- **Page**: `vendor/pushword/core/src/Entity/Page.php` — slug, title, h1, mainContent (Markdown), host, locale, parentPage, mainImage, tags, customProperties
- **Media**: `vendor/pushword/core/src/Entity/Media.php` — fileName, alt, alts (localized), mimeType, dimensions, tags
- **User**: `vendor/pushword/core/src/Entity/User.php` — email, roles, apiToken (designed as MappedSuperclass — extend in your app)

Entity class docblocks list traits, fields, and relations.

### Key documentation (in `vendor/pushword/docs/content/`)

- `architecture.md` — bundle map and packages table
- `extensions.md` — what each extension does + extension points
- `media-api.md` — REST API for media upload/read/delete
- `ai-index.md` — generate CSV indexes for AI content discovery

### Multi-locale content

Pushword uses the `host` field for multi-site/multi-locale. Each locale is a separate host. Pages link across locales via the `translations` relation (a `ManyToMany` on `Page`, defined in `PageTrait/PageI18nTrait.php`), not a custom property. Slugs should be localized per language.

### Common commands

- `pw:flat:sync` — sync flat files to/from database
- `pw:ai-index` — generate CSV index for AI tools
- `pw:static` — generate static HTML
- `pw:media:normalize-filenames` — normalize media filenames
- `pw:page-scan` — scan for dead links and issues
- `pw:link:graph` — internal link graph: inbound/outbound links, depth, orphans
- `pw:user:token {email}` — get API bearer token

#### Agent-optimized output

`pw:page-scan`, `pw:link:graph`, `pw:flat:sync`, `pw:flat:lint`, `pw:static`,
`pw:image:cache` and `pw:quiz:validate` auto-detect when an AI agent runs them (via the same env vars as
laravel/agent-detector) and emit a single compact JSON line instead of progress
bars, colors, PID/timing/memory chatter — far cheaper to parse. Each starts with
`{"tool": "...", "result": "passed|failed|done|running", ...}`. Force it with
`--format=agent` (JSON) or `--format=text` (human); default is `--format=auto`.
`pw:media:debug --json` and `pw:quiz:schema` are already JSON.

### Quality gates

- PHPStan (`composer stan`), php-cs-fixer (`composer format`), Rector (`composer rector`)
- PHPUnit (`composer test` or `composer test-filter ExampleTest`)
- Clear cache after modifications: `php bin/console cache:clear`
- Use Tailwind CSS 4 for frontend

### PHP conventions

- PHP 8.4+
- `camelCase` methods/variables, `SCREAMING_SNAKE_CASE` constants
- Fast returns, trailing commas, 4-space indent
- PHPDoc only when necessary (Collection generics)
- Twig: 2-space indent, camelCase translation keys
