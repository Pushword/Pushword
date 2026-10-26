---
title: 'sites created since 1.0.76 can delete the unused demo video; newsletter admin assets moved to their bundle'
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

**Concerns:** `pushword/admin`, `pushword/core`, `pushword/newsletter`

## Motion demo video no longer copied into new sites

New sites no longer receive `media/repurpose-nord.mp4`, a 7 MB development video with
no media entry. A site created from 1.0.76 on can delete that file unless its media
library lists it.

## Early session save limited to the admin

Since 1.0.76 every started session was saved before its controller ran, and Symfony
then silently skips `migrate()` and `invalidate()`. Only `/admin` requests are saved
early now; login, logout and site controllers keep their session open. Nothing to do.

## Newsletter admin assets

The newsletter screens' styles and scripts left the admin bundle for
`/bundles/pushwordnewsletter/admin.css` and `admin.js`, loaded by the newsletter layout only.
**Sites serving copied bundle assets:** refresh them with `assets:install`; installations using symbolic links need no refresh.
