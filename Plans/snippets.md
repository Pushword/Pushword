# Plan — Reusable Snippets (`pushword/snippet`)

> Status: idea / draft. Inspired by Sulu snippets, adapted to Pushword's flat-first philosophy.

## Goal

Provide **editor-owned, reusable content fragments** (CTA, author box, pricing
table, footer note…) that can be included in many pages, edited from the admin
*and* as flat files, translated per host, and versioned — **without** the dev
having to touch a Twig template.

This is the real gap vs Sulu: a Twig `{% include %}` is *code* owned by the dev;
a snippet is *content* owned by the editor/AI.

## Key decision

**New package `pushword/snippet` with its own `Snippet` entity — NOT a flagged
`Page`.** Reusing `Page` (non-published, no route) would pollute the page tree,
the sitemap, `pages_list`, search, redirections, etc. A dedicated entity keeps
things clean ("éviter le bordel").

## Design

### Entity `Pushword\Snippet\Entity\Snippet` (MappedSuperclass, like Page/Media)

Compose existing shared traits to stay consistent:
- `IdTrait`, `HostTrait` (per-locale = per-host, same model as Page), `TimestampableTrait`
- `Slug` / unique key (the reference used in pages, e.g. `cta-newsletter`)
- `content` (Markdown — same storage as `Page.mainContent`)
- `name`/label for the admin list
- `TagsTrait` (optional, for organisation)
- `ExtensiblePropertiesTrait` (optional custom properties)

No `parentPage`, no `slug` route, no `metaRobots`, no `mainImage` requirement —
deliberately lighter than `Page`.

### Rendering — reuse the existing filter pipeline

A snippet's `content` must pass through the **same EntityFilter pipeline**
(`packages/core/src/Component/EntityFilter/`) as a page: Markdown → Twig →
multisite links → ShowMore… So Markdown + EditorJS compatibility comes for free
because it's the same rendering path as pages.

Expose via:
- **Twig**: `{{ pw_snippet('cta-newsletter') }}` (new Twig function in this package,
  mirroring `pages_list()` in `core/src/Twig/PageExtension.php`).
- **EditorJS block**: a "Snippet" tool in `admin-block-editor` (mirror the
  `PagesList` tool) that stores `<!-- snippet:cta-newsletter -->` in Markdown and
  resolves it on render — keeps Markdown round-trippable.

### Flat-file compatibility (`pushword/flat`)

Snippets sync as Markdown + YAML frontmatter, in a dedicated dir
(e.g. `pages/../snippets/{host}/{slug}.md`), exactly like pages. Add a
`pw:flat:sync` entity target `snippet`. → editable by humans and AI, git-versioned.

### Admin (`pushword/admin`)

EasyAdmin CRUD for `Snippet` (clone the Page CRUD, strip page-only fields).
Block editor + advanced-main-image plug in via the existing field-config events
if needed.

### Versioning (`pushword/version`)

The version subscriber listens on entity update/persist — make it entity-agnostic
or add `Snippet` so snippets get the same version/restore history as pages.

## Package skeleton (match `pushword/version`)

```
packages/snippet/
  composer.json          # name: pushword/snippet, type: symfony-bundle, require pushword/core
  src/PushwordSnippetBundle.php
  src/Entity/Snippet.php
  src/Repository/SnippetRepository.php
  src/Twig/SnippetExtension.php           # pw_snippet()
  src/DependencyInjection/{Configuration,PushwordSnippetExtension}.php
  src/config/services.php
  src/templates/...
  src/translations/messages.{en,fr}.yaml
  tests/
```

## Open questions

- Reference syntax in Markdown: HTML comment `<!-- snippet:slug -->` (consistent
  with the `comment:` DSL already used by `pages_list`) vs a Twig call. Comment is
  more flat/AI-friendly.
- Locale fallback: if a snippet has no translation for the current host, fall back
  to default host or render nothing?
- Should `customProperty` snippets support parameters (e.g. variant)? Probably out
  of scope for v1 — keep it dumb content reuse.

## Non-goals (v1)

- No parameterised/templated snippets, no Smart-Content-style queries (that's
  `pages_list`), no nested snippet-in-snippet recursion guard beyond a depth limit.
