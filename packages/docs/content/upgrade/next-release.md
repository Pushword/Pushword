---
title: 'invalid media are refused on every write path; media removal can be vetoed; license values typed with an upload are kept'
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

**Concerns:** `pushword/admin`, `pushword/admin-block-editor`, `pushword/api`, `pushword/core`

## Media validated on every write path

The multi-upload, inline editing, the block editor and `POST`/`PATCH /api/media` now validate the whole media, as the admin form did, and refuse an invalid one (`422`) instead of saving it.
**Sites mapping their own constraints onto `Media`:** these now apply on those paths too — see [Media rules](/media-rules). **API clients:** handle `422 {"error": "validation", "violations": […]}`.

## Media removal can be vetoed

A `MediaPreRemoveEvent` listener can keep a media from the admin delete, the multi-upload delete, `DELETE /api/media` (`409`) and `pw:media:clean-unused`, which reports it under `refused`. Nothing to do without a listener — see [Media rules](/media-rules#vetoing-a-removal).

## License values submitted with an upload

A credit or other license value typed in the admin form, or sent in `customProperties` with an API upload, is now kept instead of being replaced by the file's embedded rights. Nothing to do — see [image license](/image-license#what-happens-on-upload).
