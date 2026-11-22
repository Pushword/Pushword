---
title: 'repurpose rejects slide images missing from the media library and fonts not installed; contrast warnings name the text field'
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

**Concerns:** `pushword/repurpose`

## Slide images must exist in the media library

A slide image that names no JPEG, PNG, GIF or WebP of the media library is now a
validation error, no longer a grey placeholder. The studio lists such an image as a
violation: pick an existing one before saving.

## Contrast warnings name the text field

Warnings now cover the tagline, free texts and highlights, with paths such as
`slides[0].title` or `slides[0].texts[1]` instead of `slides[0]`. Update any
script that matches the old path.

## Fonts not installed fail validation

A font pairing whose files are missing is now a validation error, no longer a
silent Roboto fallback. The studio and the API download missing Google Fonts
families themselves. **Sites validating on the command line:** run the command
the error names, `pw:repurpose:fonts <pairing>` or `--heading=… --body=…`.
Pairings that set a heading family as body text (`abril-fatface-poppins`,
`merriweather-lora`…) now download its regular weight instead of rendering it bold.
