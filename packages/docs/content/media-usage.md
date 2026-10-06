---
title: 'Where is this media used?'
h1: 'Media usage'
publishedAt: '2026-08-04 10:00'
toc: true
---

Pushword records which pages reference which media, for admin filters, cleanup
commands and tags inherited from pages.

## What counts as a use

Three things put a row in the `media_usage` table, and the `source` column says which:

| `source`     | Where the reference lives                                            |
|--------------|----------------------------------------------------------------------|
| `content`    | the page's Markdown body — `![alt](…)`, `{{ image(…) }}`, a gallery  |
| `main_image` | the page's `mainImage` relation                                       |
| `property`   | anywhere in the page's custom properties                              |

A media referenced only from a **Twig template** — a navbar logo, an Open Graph
fallback — has **no row**: templates are not scanned. "No page references this media"
is therefore not "this media is unused"; read every screen and command below that way
before deleting anything.

## Keeping it current

Every page write — admin, import or API — updates that page's rows, and a removed page
drops them. Creating a media also re-extracts the pages naming its filename, so a page
written before its image was uploaded, or a media deleted and re-uploaded under the same
name, is tracked.

Build the table once, and rebuild it whenever rows reached the database without going
through the listener:

```bash
php bin/console pw:media:usage:rebuild
```

Run it after restoring a database or a bulk write that bypassed the listener; it
rewrites the whole table. References resolve by whole **filename**, current or previous
(a page pointing at a renamed file still uses it); `myphoto.jpg` is not a use of
`photo.jpg`.

## In the admin

The media list has a **Referenced by a page** filter (referenced or not). A media's edit screen
lists the pages using it, each tagged *content*, *main image* or *property*.

## Cleaning up

```bash
php bin/console pw:media:clean-unused            # lists, changes nothing
php bin/console pw:media:clean-unused --force    # deletes the rows and their files
```

Read the dry-run list before forcing: a template's logo appears there like a forgotten
upload. The command refuses to run on an empty usage table — run
`pw:media:usage:rebuild` first.

## Tags inherited from pages

A media carries two independent sets of tags:

- **`tags`** — what somebody typed on the media. Yours, never touched by anything here.
- **`pageTags`** — the union of the tags of the pages using it. Derived and read-only,
  rewritten whenever the usage or a page's tags change.

Each has its own admin filter: *Tags* and *Tags from the pages*.

## For AI tools

`pw:ai-index` reads the same table: `medias.csv` gets a `usedInPages` column and
`pages.csv` a `mediaUsed` one. An unbuilt table exports every media as unused — see
[the rebuild](#keeping-it-current).
