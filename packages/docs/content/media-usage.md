---
title: 'Where is this media used?'
h1: 'Media usage'
publishedAt: '2026-08-04 10:00'
toc: true
---

Pushword stores, in the database, which pages reference which media — so the admin can
filter on it, the CLI can act on it, and a media can inherit the tags of the pages
showing it.

## What counts as a use

Three things put a row in the `media_usage` table, and the `source` column says which:

| `source`     | Where the reference lives                                            |
|--------------|----------------------------------------------------------------------|
| `content`    | the page's Markdown body — `![alt](…)`, `{{ image(…) }}`, a gallery  |
| `main_image` | the page's `mainImage` relation                                       |
| `property`   | anywhere in the page's custom properties                              |

A media referenced from a **Twig template** — a navbar logo, an Open Graph fallback —
has **no row**. Nothing scans templates, and nothing can, since a template renders
against pages that do not exist yet.

That single limit decides what the whole feature is allowed to say. "No page references
this media" is a fact; "this media is unused" is a guess. Every screen and every command
below is worded the first way, and you should read them that way before deleting
anything.

## Keeping it current

The table is written on every page write — a page saved, imported, or edited through the
API updates its own rows, and drops them when it is removed. Nothing to schedule.

It has to be built once, though, and rebuilt whenever rows reached the database without
a page write to notice:

```bash
php bin/console pw:media:usage:rebuild
```

Run it after the upgrade that introduced the table, after restoring a database, and
after a bulk import that created pages before their media existed. It scans the whole
corpus in batches and rewrites the table from scratch.

The scan resolves a reference by its **filename**, including filenames a media used to
carry: a page still pointing at a renamed file renders it, so it still uses it. It
matches whole filenames only — `myphoto.jpg` is not a use of `photo.jpg`.

## In the admin

The media list gained a **Referenced by a page** filter, with both directions.

A media's edit screen lists the pages using it, each tagged with why: *content*, *main
image* or *property*.

## Cleaning up

```bash
php bin/console pw:media:clean-unused            # lists, changes nothing
php bin/console pw:media:clean-unused --force    # deletes the rows and their files
```

The dry run is the default on purpose. Read its list before forcing it — a template's
logo appears there exactly like a forgotten upload.

The command refuses to run against an empty usage table, because "nothing has been built
yet" and "every media on this site is orphaned" look identical from the inside, and
guessing wrong deletes the whole library. Build it first.

## Tags inherited from pages

A media carries two independent sets of tags:

- **`tags`** — what somebody typed on the media. Yours, never touched by anything here.
- **`pageTags`** — the union of the tags of the pages using it. Derived, read-only,
  rewritten whenever the usage or a page's tags change, and emptied when the last page
  using the media stops.

They are two columns and two admin filters — *Tags* and *Tags from the pages* — so that
"I tagged this image" and "the pages showing it are tagged" stay two questions you can
ask separately. Merging them would leave neither answerable, and would let a page retag
quietly rewrite a human decision.

## For AI tools

`pw:ai-index` reads the same table: `medias.csv` gets a `usedInPages` column and
`pages.csv` a `mediaUsed` one, for free and without rescanning. A media the table does
not know about exports as used by nothing — see [the rebuild](#keeping-it-current).
