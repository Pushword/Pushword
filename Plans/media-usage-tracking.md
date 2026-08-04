# Plan — Where is this media used?

## Goal

Know, from the database, which pages use a given media — and act on the answer:
filter unused media in the admin, clean them from the CLI, and inherit tags from
the pages that use them.

Covers four roadmap entries under `[Core] / [Admin] Media`, plus "Tags importés
depuis les pages qui utilisent le média".

## Current state (baseline)

Usage is computed in exactly one place, for one purpose, and thrown away:

- `packages/flat/src/Command/AiIndexCommand.php:182-195` — `extractMediaUsed()`
  loops every page × every media filename and does `str_contains($content, $name)`.
  O(pages × media) string scans per run, only to write `medias.csv`.
- Nothing persists it. No `Media→Page` relation, no admin filter, no query.

Adjacent commands exist but answer different questions:

- `pw:media:clean-missing` — media rows whose **file** vanished.
- `pw:media:clean-duplicates` — media rows sharing file content.
- `pw:media:debug` — does this filename resolve.

None of them can say "no page references this file".

## The hard part: what counts as a use

Decide this before touching the schema, because it decides how much the feature
can promise:

1. **Markdown body** — `![alt](/media/…)`, `{{ gallery({...}) }}`, `{{ image(…) }}`.
   Resolvable by parsing the stored `mainContent`.
2. **Entity fields** — `Page.mainImage`, and whatever `advanced-main-image` adds.
   Already a real relation, cheap.
3. **Custom properties** — a media referenced from a schema-declared page property.
4. **Templates** — a logo in `_navbar.html.twig`, an OG fallback. **Not
   discoverable** by scanning content, and the roadmap already flags the question.

1–3 are answerable. 4 is not, which means "unused" can only ever mean "not
referenced by any page" — never "safe to delete". The CLI and the admin filter
must both say so, or someone will delete a template's logo.

## Steps

1. **Model the relation.** A `media_usage` join table (media_id, page_id, source
   enum: content | main_image | property) beats a `ManyToMany` — the source
   column is what lets the admin explain *why* a media is used, and lets a
   template-sourced entry be added later without a migration. No Doctrine
   migrations in this repo: `doctrine:schema:update --force`.
2. **Populate on write, not on read.** A Doctrine listener on `Page` postPersist
   / postUpdate re-extracts that page's media references and rewrites its rows.
   Reuse the extraction the renderer already does rather than a second regex
   dialect — `LinkCollector` is the closest existing precedent.
3. **Backfill command** — `pw:media:usage:rebuild`, full scan, for the first run
   and after a bulk flat import. This is where `AiIndexCommand`'s loop moves;
   it should then read the table instead of rescanning (its `medias.csv` gets
   the same data for free).
4. **Admin filter.** `MediaCrudController` already builds filters
   (`packages/admin/src/Controller/MediaCrudController.php:223-263`) — add
   "unused", worded as "not referenced by a page".
5. **`pw:media:clean-unused`.** Dry-run by default, `--force` to delete, and it
   must refuse to run if the usage table was never built. Follow
   `CleanMissingMediaCommand`'s shape, including `AgentOutputTrait`.
6. **Tags from pages** (the separate roadmap entry). Once the relation exists this
   is a small command: union the tags of the pages using a media, write them onto
   the media. Decide whether it is a one-shot import or a live derivation —
   one-shot is honest, live means a page retag silently rewrites media tags.

## Verification

- A page whose content is edited to drop an image loses its usage row.
- A media used only by `mainImage` is not reported unused.
- Bulk flat import does not run the listener once per page per media
  (see `perf-flat-pagescan` — this is exactly the shape that regressed before).
- `pw:media:clean-unused --dry-run` on the dev-app lists nothing surprising.
