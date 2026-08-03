---
title: 'the static search endpoint ships a populated index, and an unreadable index rebuilds itself'
run: 'pw:static'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

**Concerns:** `pushword/search`

## The exported `search/loupe.db` was empty

Loupe indexes in WAL mode: right after a write the documents sit in
`loupe.db-wal` and the main file still holds none of them. The static export
copied `loupe.db` on its own, so with `static_mode: endpoint` (or `both`) every
build shipped a valid but **empty** index — the endpoint returned no results for
any query. The export now folds the log back in before copying.

Nothing broke visibly until now because `search.json` is built from the documents
in memory, so the client-side fallback always worked; only the PHP endpoint was
affected.

Run `pw:static` once to publish a real index. Sites on `static_mode: json` were
never affected.

## An unreadable index resets itself instead of failing for good

Loupe runs SQLite with `synchronous = OFF` — a search index is rebuildable, so it
trades durability for write speed. The cost is that a writer killed mid-checkpoint
(power loss, OOM kill, an interrupted deploy) leaves `loupe.db` unreadable, and
every later open then failed with `SQLSTATE[HY000] ... 26 file is not a database`.
Because the index is built on post-generate, that took the whole `pw:static` build
down, long after whatever caused the damage, and nothing short of deleting the file
by hand brought it back.

Such an index is now dropped and recreated on open, with a warning in the log.
Recovery is automatic but the index comes back **empty**: run `pw:search:index` to
repopulate it if the warning appears outside a build (a `pw:static` run refills it
on its own).

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
