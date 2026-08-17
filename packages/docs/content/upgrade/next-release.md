---
title: 'pw:image:cache counts what its parallel workers reported, and a batch that did nothing fails the run'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

**Concerns:** `pushword/core`

## `pw:image:cache -p` counts the images its workers reported

A parallel run derived its processed count as `stale images - errors`, so a worker that exited having done nothing was still credited with its whole batch. The parent now counts the per-image lines each worker prints: an image no worker accounted for is reported as an error instead of assumed done, and a failure inside a batch arrives named rather than as `batch: exit code 1`.

**Affected:** sites running with `-p` above 1, or on 8 cores or more, where the automatic worker count is already above 1. Expect the number to drop, and a run that was silently losing a batch to now exit 1. The rc882 note called this path already correct; it was not.

<!--
The upgrade note for the next release. `.scripts/release` renames this file to
`upgrade/rc<N>.md`, adds its row to the table in `upgrade.md` and empties it back
to this scaffold, at the tag.

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
