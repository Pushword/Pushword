---
title: 'super administrators can log in as an editor; custom admin pages extending the EasyAdmin layout miss its banner; the review filter on `referring` is labelled like its form field; a same-line class starting with a digit or an accent stays literal text'
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

**Concerns:** `pushword/admin`, `pushword/conversation`, `pushword/core`, `pushword/static-generator`

## Log in as an editor

Super administrators can now [browse the admin as an editor](/authentication#impersonation) from the user list, under an amber banner on every admin page.

**Sites with custom admin templates extending `@EasyAdmin/layout.html.twig`.** Extend `ea().templatePath('layout')` instead, or those pages show no banner.

## Review filters

The review list filters ignore case on every database, and the `referring` filter is now labelled like its form field ("Referring") instead of "Trip code".

**Sites that call `referring` something else.** Override `adminConversationReferringLabel` in your translations; it renames the filter and the form field together.

## A host with no published page no longer stops `pw:static`

Without a host argument, `pw:static` now skips a host that has an `index.html` but no published page left instead of stopping there: that host keeps its published site, the error names it, the other hosts are built, and the command still exits non-zero. Nothing to do.

## Same-line block attributes

`{#id} Text` and several attributes on one line (`{#tip .note} Text`) now render instead of failing with a Twig error, inside blockquotes too; `\{.class} Text` stays text.

**Pages with a same-line class starting with a digit, a dash and a digit, or an accent (`{.1abc} Text`).** The marker now stays literal text, as in CommonMark: rename the class to start with a letter or `_`.
