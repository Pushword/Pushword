---
title: 'parallel static workers each get their own opcache file cache'
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

- `title:` — the "What changed" cell of the index table. One line, lower case,
  written from the site's side ("the newsletter form is fetched, and CSRF-protected")
  rather than the diff's ("refactor NewsletterFormController"). Required as soon as
  the note has a section; the release stops if it is still empty.
- `run:` — the command(s) the release expects, without `php bin/console`. Omit the
  key when there is none. A list runs in the order given.
- `**Concerns:**` — first line of the body, listing every package a site has to
  install to be affected. Alphabetical, full composer names, `@pushword/js-helper`
  last. Add the packages your change touches to the line, keep the others.
- One `##` section per change, saying what breaks and what to do about it.

Several changes land here between two tags: append to the file, do not replace it.
-->

**Concerns:** `pushword/static-generator`

## The opcache file cache moved under `var/cache/opcache/w{n}`

`pw:static` used to point every parallel worker at one shared
`var/cache/opcache`. Concurrent processes writing a single opcache file cache
segfault the workers on some PHP builds (reported on CloudLinux alt-php 8.4.3:
6 workers out of 8 killed on every run, exit 139). Each worker now writes its own
directory.

Nothing to run: the new directories are created on the next build. Two things to
know:

- The old shared entries are now orphaned — delete `var/cache/opcache` once to
  reclaim the space (safe at any time; the next build refills what it needs).
- Disk grows to roughly one full compiled-code cache per worker. If the build
  machine is tight on disk, cap the workers with `pw:static --workers=N`.
