---
title: 'the conversation origins key is `conversation_possible_origins`; `pw:ai-index` without a host exports every site; Mermaid code blocks render as diagrams'
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

**Concerns:** `pushword/conversation`, `pushword/core`, `pushword/flat`

## Conversation origins key

The global `conversation: possible_origins:` key was never read. It is now
`conversation_possible_origins`, the key sites already set per app.
**Affects sites that set `possible_origins` under `conversation:`.** Rename it, or
`cache:clear` fails on the unknown key.

## AI index scope

`pw:ai-index` without a host (or with `""`) exports the pages of every site, not only
the default one, and `pages.csv` gains a trailing `host` column. Pass the host to keep
a single-site export.

## Mermaid diagrams

Fenced `mermaid` blocks now render as diagrams in the default theme.
Custom themes must retain the Mermaid assets; static exports must include
`bundles/pushwordcore/mermaid` in `static_assets` if they do not copy `bundles`.
Exclude `code.language-mermaid` from syntax highlighting. See [Mermaid diagrams](/markdown-block#mermaid-diagrams).
