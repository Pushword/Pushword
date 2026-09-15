---
title: 'flat imports include new pages in the generated index; `{#id}` block attributes apply before any block, not only headings; `> [!question]` notices fold their answer'
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

**Concerns:** `pushword/core`, `pushword/flat`

## Flat page index

`pw:flat:sync --mode=import` now includes newly created pages in `index.csv` and `index.draft.csv` on the first run. Nothing to do.

## `{#id}` block attributes

`{#id}` and `{#id .class}` on the line before a block now apply to it, like `{id=…}` already did and like CommonMark's attributes extension. They only worked before a heading until now. Check pages where a line starting with `{#` precedes a paragraph or a list and was meant to stay visible.

## `> [!question]` notices

A `> [!question]` blockquote now renders as a folded `<details>` carrying schema.org
`Question` microdata, instead of the generic notice box. Pages using `question` as a
plain label and wanting the box back rename it. The Markdown fragment cache version was
bumped, so the first render after the upgrade rebuilds it — nothing to run.
