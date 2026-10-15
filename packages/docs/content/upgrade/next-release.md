---
title: 'page and link choices are clearer; page links preserve their host'
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

**Concerns:** `pushword/admin`, `pushword/admin-block-editor`, `pushword/core`

## Admin page settings

The [parent-page chooser](/extension/admin#parent-page) shows titles and paths separately and ranks exact slugs first. It stays within the settings panel, and the last sidebar panel no longer stretches. Nothing to do.

## Block-editor page links

The [link tool](/extension/admin-block-editor#insert-a-link) searches the current site's pages by title or path and keeps the complete address visible. Nothing to do with the default widget.
**Sites replacing `editorjs_widget.html.twig`:** provide `window.pageLinkList` as described in the [link configuration](/extension/admin-block-editor#link-tool) to include page titles; existing `window.pagesUriList` integrations still work.

## Page links on prefixed routes

On a gateway route such as `/site-a/`, `page(otherPage)` now uses `otherPage`'s host prefix, for example `/site-b/slug`. Nothing to do.
