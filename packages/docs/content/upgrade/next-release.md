---
title: 'saving a page in the admin without editing it no longer counts as an edit; a page imported from a flat file gets an author, and editing a page no longer claims its authorship'
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

**Concerns:** `pushword/admin`, `pushword/core`, `pushword/flat`

## A page imported from a flat file gets an author

The front matter already carried the editor (`editedBy: someone@example.tld`); the import
now resolves it back to that user instead of filing the email away as a custom property. A
file naming nobody is attributed at creation to the site's first super admin — name
another with `flat.default_editor`, and see [the extension doc](/extension/flat#who-an-imported-page-belongs-to).

## Editing a page no longer claims its authorship

`createdBy` was backfilled on every save, so the first person to edit a page imported or
written through the API became its recorded creator. It is set at creation only now.
Nothing to do — pages already mis-attributed keep whoever was recorded.

## Saving a page without editing it no longer counts as an edit

Two fields came back from an untouched form different from what was stored: the
publication date lost its seconds to a minute-precision widget, and the always-empty edit
message box cleared the message the previous edit left. The save was a real one — a bumped
`updatedAt` moving the page to the top of the admin list, a version, a flat export, a
purged static cache. Nothing to do.
