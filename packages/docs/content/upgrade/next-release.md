---
title: 'page saves invalidate their cache before redirecting; search finds French words behind an elision, after an index rebuild'
run: pw:search:index
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

<!--
The upgrade note for the next release. `.scripts/release` renames this file to
`upgrade/<version>.md`, adds its row to the table in `upgrade.md` and empties it
back to this scaffold, at the tag.

Write here, in the same commit as the change, whenever a release asks something of
a site that upgrades: a command to run, a config key to set, a template to copy, a
behaviour that changed under an unchanged call. A change `composer update` fully
absorbs needs no note.

Keep it short: what changed, and what to do about it. A note is a checklist, not a
changelog and not a post-mortem — no cause, no code path, no story of the bug. That
belongs in the feature doc, which you link to instead.

- `title:` — the "What changed" cell of the index table. One line, lower case,
  written from the site's side ("the newsletter form is fetched, and CSRF-protected")
  rather than the diff's ("refactor NewsletterFormController"). Several changes: one
  short clause each, semicolon-separated, naming only those that ask something.
  Required as soon as the note has a section; the release stops if it is still empty.
- `run:` — the command(s) the release expects, without `php bin/console`. Omit the
  key when there is none. A list runs in the order given.
- `**Concerns:**` — first line of the body, listing every package a site has to
  install to be affected. Alphabetical, full composer names, `@pushword/js-helper`
  last. Add the packages your change touches to the line, keep the others.
- One `##` section per change, five lines at most: one sentence for what changed, a
  bold line for who is affected when only some sites are, then the action — a command,
  a config key, an edit to make. Nothing to do: say so in the sentence and stop.

Several changes land here between two tags: append to the file, do not replace it.
-->

**Concerns:** `pushword/search`, `pushword/static-generator`

## Saved pages are immediately current

With `cache: static`, page saves now invalidate the old HTML and compressed files before redirecting, even when Messenger refreshes are asynchronous. Requests use PHP until the worker rebuilds the cache. Publication holds and bulk imports preserve their files. No configuration change is required. See [Page Cache](/extension/page-cache).

## Search splits French elisions

`acompte` now finds "l’acompte", and `l’acompte` finds "un acompte". Until the index is
rebuilt, a query with an elision misses the pages indexed before the upgrade: run
`pw:search:index` (`pw:static` rebuilds on its own). New optional settings,
`search_stop_words` and `search_matching_strategy`, keep a word like "de" from listing
every page ([Search](/extension/search#french-text-and-natural-questions)).
