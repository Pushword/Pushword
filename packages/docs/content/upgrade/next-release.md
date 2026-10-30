---
title: 'native accelerators avoid unused analysis and preserve control-character serialization; page corpus checks use a covering index; full static rebuilds reuse unchanged compressed files and HTML minification'
run: 'doctrine:schema:update --force'
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

**Concerns:** `pushword/core`, `pushword/static-generator`

## Optional native accelerators

Content analysis is deferred on pages without a TOC; HTML minification now matches PHP's control-character serialization.
**Only sites using the optional Rust binaries are affected.**
Rebuild and redeploy `pushword-content-analyzer` and `pushword-html-minifier` with their package's `rust/Makefile`.
Control-character edge cases use PHP serialization to match the installed libxml version.
PHP remains the default; no configuration change is required.

## Published page corpus index

Corpus freshness checks now use a covering index without loading parent or image rows.
Run `php bin/console doctrine:schema:update --force` in every installed application to create `idx_page_corpus_state`.

## Unchanged compressed files

Full static rebuilds reuse existing compressed files when the newly rendered bytes are identical.
Pages still render on every full build; changed pages and missing compressed files are compressed normally.
No action or configuration change is required.

## Cached HTML minification

Static builds reuse minified HTML when the freshly rendered input and minifier identity are unchanged.
The cache survives `cache:clear` and shares `cache.pushword_markdown` with Markdown fragments.
No action or configuration change is required.
