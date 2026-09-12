---
title: 'PHP files use strict types; optional Rust minification and content analysis'
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

**Concerns:** `pushword/core`, `pushword/dev-app`, `pushword/static-generator`

## PHP strict types

Pushword PHP files now declare strict types, and the shared PHP-CS-Fixer rules preserve existing declarations.
**Sites with custom PHP extensions:** run your tests and correct scalar type mismatches in overrides, callbacks, and service integrations.
If you use the provided Rector configuration, copy `vendor/pushword/dev-app/rector.php` into your project, preserving local customizations, to enable strict-type declarations.

## Optional native HTML minification

Static generation can use an experimental Rust minifier; PHP remains the default and requires no new tool.
**Sites opting into Rust:** build the executable, set `static_generator.native_html_minifier` to its trusted path, then clear the Symfony container cache in the generation environment.
See [native acceleration](../native-acceleration.md) for installation, fallback and measured limits.

## Optional native content analysis

Content splitting can use Rust while retaining PHP fallback and existing block markers; PHP remains the default.
**Sites opting into Rust:** build `vendor/pushword/core/rust`, deploy `pushword-content-analyzer`, set `pushword.native_content_analyzer` to its trusted path, then clear the Symfony container cache.
See [native acceleration](../native-acceleration.md) for the supported HTML boundary and checks.
