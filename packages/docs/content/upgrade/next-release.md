---
title: 'the database now knows which pages use which media'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

<!--
The upgrade note for the next release. `.scripts/release` renames this file to
`upgrade/rc<N>.md`, adds its row to the table in `upgrade.md` and empties it back
to this scaffold, at the tag.

Write here, in the same commit as the change, whenever a release asks something of
a site that upgrades: a command to run, a config key to set, a template to copy, a
behaviour that changed under an unchanged call. A change `composer update` fully
absorbs needs no note.

- `title:` — the "What changed" cell of the index table. One line, lower case,
  written from the site's side ("the newsletter form is fetched, and CSRF-protected")
  rather than the diff's ("refactor NewsletterFormController"). Required as soon as
  the note has a section; the release stops if it is still empty.
- `run:` — the command(s) the release expects, without `php bin/console`. Omit the
  key when there is none. A list runs in the order given.
- `**Concerns:**` — first line of the body, listing every package a site has to
  install to be affected. Alphabetical, full composer names, `@pushword/js-helper`
  last. Add the packages your change touches to the line, keep the others.
- One `##` section per change, saying what breaks and what to do about it.

Several changes land here between two tags: append to the file, do not replace it.
-->

**Concerns:** `pushword/admin`, `pushword/core`, `pushword/flat`

## The database now knows which pages use which media

Media usage was computed nowhere and stored nowhere: `pw:ai-index` scanned every
media against every page and threw the answer away. It is a table now —
`media_usage`, one row per (media, page, source) — filled on every page write.

**This adds one table and one column, so run:**

```bash
php bin/console doctrine:schema:update --force
php bin/console pw:media:usage:rebuild
```

The rebuild is what fills the table for content that already exists; until it has
run, every media reads as referenced by nothing. Run it again after any bulk write
that reached the database behind the listener — a restore, or an import run against
media that did not exist yet.

**In the admin**, the media list gained a *Referenced by a page* filter, and a
media's page panel now says *why* each page uses it: content, main image, or a
custom property.

**From the CLI**, `pw:media:clean-unused` lists the media no page references, and
removes them with `--force`. Read the list first. **It cannot mean "safe to
delete":** nothing scans Twig templates, so a navbar logo or an OG fallback is
listed exactly like a forgotten upload. The command refuses to run at all against
an empty usage table, since that state is indistinguishable from a site whose every
media is orphaned.

**Media inherit the tags of the pages using them**, in their own `pageTags` column
with its own admin filter — never merged into the tags somebody put on the media by
hand. It is derived, not editable: a page retag rewrites it, and it drops back to
empty when the last page using the media stops.

One behaviour changed under an unchanged call. `PageRepository::getPagesUsingMedia()`
reads the table instead of running a `LIKE` over `mainContent`, so it is both faster
and stricter — `myphoto.jpg` no longer counts as a use of `photo.jpg` — and it
answers with nothing until the rebuild has run.
