---
title: 'rendered text gets locale-aware typography (smart quotes, non-breaking spaces…); sources stay plain — the editor no longer writes typographic characters and the flat export straightens them'
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

**Concerns:** `pushword/admin-block-editor`, `pushword/core`, `pushword/flat`

## Rendered text gets locale-aware typography

A new `Typography` entity filter runs after markdown rendering: smart quotes per
locale, curly apostrophes, ellipsis, `×`, `©`/`®`/`™` and French non-breaking
spaces before `: ; ! ?` — dashes are never touched, and `pre`/`code`/`svg`/
`script` content is left alone ([doc](/markdown-block)). Rendered HTML therefore
changes on every site: tests asserting rendered content will need their
expectations updated. Opt out per page with `filter_typography: 0`, or per site
by overriding the `filters` config key (the filter is part of the default
`main_content`, `name`, `title` and `string` chains).

## Sources stay plain: the editor and the flat export converge on ASCII

The block editor no longer writes typographic characters on save (smart quotes,
dashes, ellipsis, `×`, `™`, non-breaking spaces), and the flat export now also
straightens no-break spaces (U+00A0, U+202F) and drops soft hyphens, on top of
the quotes it already normalized. Nothing to run: existing content converges at
the next export, and render output is identical either way since the filter is
idempotent.
