---
title: 'markdown Tempest cannot render uses CommonMark again instead of a 500; a page failing in a parallel `pw:static` worker fails the build; static `.htaccess` redirects match the exact path'
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

## Markdown Tempest cannot render uses CommonMark again

Syntax Tempest declines, such as `[link](/url){rel="encrypt" class="x"}`, renders with CommonMark again instead of failing the page with a 500. Nothing to do.

## A failed page fails a parallel `pw:static`

A page that fails to render in a parallel worker now fails the build with exit code 1 and keeps the previous export in place, as a sequential build already did.
**Affects sites whose build reported success while pages were missing.** Fix the page the error names, then rerun `pw:static`.

## Static `.htaccess` redirects match the exact path

Redirections are written as `RedirectMatch 301 ^/old/?$ /new`: `/old` no longer redirects `/old/child` to `/new/child`.
**Affects sites relying on one redirect to move a whole section.** Add that rule to your `htaccess.twig` override, in the `before_rules` block.
