---
title: 'quiz completion events may report an assessment; an assessment''s last button reads "See my result"; pushword/core no longer installs piedweb/extractor'
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

**Concerns:** `pushword/core`, `pushword/quiz`.

## Quiz completion event

Since 1.0.91, `QuizCompletedEvent::getMode()` can return `assessment`, with the numeric outcome in `getResult()`, and `ResultBand::$min` is a `float`.
**Sites with a `QuizCompletedEvent` listener, or code reading `ResultBand::$min`:** handle the `assessment` mode and a float threshold. See [Quiz](/extension/quiz#numeric-assessments).

## Assessment last step

The last step of a numeric assessment now reads "See my result" ("Voir mon résultat" in French) instead of the confirm label.
To keep the previous wording, set `labels.finish` in the quiz JSON.

## piedweb/extractor dropped

`pushword/core` no longer installs `piedweb/extractor`, nor the `league/uri`, `spatie/robots-txt` and `jeremykendall/php-domain-parser` it brought along.
**Sites whose own code uses one of these packages:** require it in the site's `composer.json`.
