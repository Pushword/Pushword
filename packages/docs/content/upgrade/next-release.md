---
title: 'links to unpublished pages are restored from the pw_auth cookie instead of an auth probe; `image()` takes a `sizes` argument and defaults to `100vw`'
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

**Concerns:** pushword/core, @pushword/js-helper

## Draft links are restored from `pw_auth`, not from an auth probe

The unpublished-link restorer reads the `pw_auth=1` cookie instead of fetching `/_pushword/auth-check`, so anonymous visitors no longer trigger the 401 that browsers log as a console error and Lighthouse counts against best-practices.

**Affects sites using [Unpublished Links](/unpublished-links).** Rebuild your front assets (`yarn build`) for it to take effect — until then the old bundle keeps probing, and the endpoint keeps answering. Only `ROLE_EDITOR` gets draft links back now, where the probe answered to any fully authenticated user of the firewall covering it.

## `image()` takes a `sizes` argument, and the default is `100vw`

`sizes` now goes on the element that carries the srcset, the modern `<source>` — passed through `attr` it only ever reached the `<img>`, which no webp-capable browser reads, and arrived concatenated with the default. The default itself was a ladder announcing the breakpoint's width rather than the viewport's, which made phones download a candidate one or two steps too large; it is `100vw` now. The `<img>` behind a webp `<source>` no longer carries a `srcset` (it rendered empty, and the ladder does not exist in the source format).

**Affects every site rendering `image()`.** Nothing breaks unchanged, but `100vw` still over-serves anything narrower than the viewport: pass `sizes:` the width the element really occupies — `image(media, sizes: '(max-width: 1023px) 100vw, 773px')` — wherever an image is not full-bleed. A caller already passing `attr: {sizes: …}` keeps working and should move the value to the argument.
