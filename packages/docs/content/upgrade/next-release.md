---
title: "the link panel's style options are named in English so they can be translated; the static build's workers drop the opcache file cache that was killing them"
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

**Concerns:** `pushword/admin-block-editor`, `pushword/static-generator`

## The link tool's style options are named in English

`bouton`, `bouton outline` and `discret` are now `Button`, `Button outline` and
`Discreet` — labels the catalogue can translate, where the French ones showed through
untranslated.

**Affects sites shipping their own `editorjs_widget.html.twig`.** Rename the
`availableDesigns` keys in your copy, or its options stay in French:

```js
availableDesigns: { Button: 'link-btn', 'Button outline': 'link-btn-outline', Discreet: 'ninja' },
```

## `pw:static` workers no longer use an opcache file cache

**Affects every site building with `pw:static` on more than one worker.** The flags that
kept compiled scripts across worker lives segfault the worker on some PHP builds, losing
the build (`Worker N failed (exit 139: Segmentation violation)`). Dropped: a fresh pass is
now ~18% slower and finishes. Nothing to configure — reclaim the disk the cache still
holds:

```shell
rm -rf var/cache/opcache
```

[Opting back in by hand](/extension/static-generator#performance), for a PHP that does not
crash on it.
