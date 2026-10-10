---
title: 'the Docker image uses patched Go networking libraries; the heading tool offers the levels the editor widget lists; a block with both an anchor and a class is saved so its class renders'
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

**Concerns:** `pushword/admin-block-editor`, `pushword/core`

## Docker security update

The generated Dockerfile rebuilds FrankenPHP with Go 1.26.9, `golang.org/x/net` 0.60.0 and `golang.org/x/crypto` 0.57.0.
**For sites using the Docker skeleton:** regenerate it with `pw:docker:init --force`, then rebuild the image.
If you customized the Dockerfile, apply those builder settings manually instead of overwriting it.

## Heading levels follow the editor widget

The heading tool now offers the levels listed under `tools.header.config.levels` in the editor widget, H2 to H6 by default; a heading keeps its own level even when the list leaves it out.
**For sites overriding the `editorjs_config` block of `editorjs_widget.html.twig`:** set `levels: [2, 3, 4, 5, 6]` there to keep offering H5 and H6.

## Anchor and class saved apart

The block editor saved a block with both an anchor and a class (or an alignment) as `{#anchor.class}`, which renders as `id="anchor.class"` and no class; it now writes `{#anchor .class}`. Editing such a block rewrites it.
**For sites whose pages hold such lines:** open and save those pages in the block editor, or replace `{#anchor.class}` by `{#anchor .class}` in their content.
