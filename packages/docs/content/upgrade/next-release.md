---
title: 'audiences gain shared email design settings and Twig presets; media writes are logged on the `pushword_media` channel; file names several media share are reported'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
run: 'doctrine:schema:update --force'
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

## Shared email design

Run `php bin/console doctrine:schema:update --force` to add the audience's email design fields, including the nullable Twig preset identifier. Existing audiences retain the simple layout. Custom `/newsletter/email.html.twig` templates can use `wrapped` and `header`; registered presets can extend the default template's `email_header` and `email_footer` blocks. The live preview now opens at mobile width. See [email design](../extension/newsletter#email-design-and-live-preview).

## Media audit log

Media creations, renames and removals are logged at `info` on the `pushword_media` Monolog channel. To keep them, route the channel to its own handler: see [audit log](../storage#audit-log).

## File names several media share

`pw:image:cache` reports a file name several media hold and leaves those media alone; renaming one is refused, and removing one keeps the file. **Concerns sites that uploaded media before this release.** List them with `SELECT media, COUNT(*) FROM media GROUP BY media HAVING COUNT(*) > 1`, then remove the row whose `hash` is not the file's SHA-1. See [file names](../storage#file-names).

## Duplicate uploads in the media form

The admin media form no longer stores a file the library already holds: it opens the existing media, or selects it in the media picker. `Media::$fileNameBeforeUpdate` and `Media::setFileNameBeforeUpdate()` are removed; nothing to do unless your code read them.
