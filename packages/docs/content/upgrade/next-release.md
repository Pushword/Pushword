---
title: 'page files write dates with their offset, and a date with an offset is stored at that instant; dates without one follow the new `editorial_timezone`, to set only after a forced export; the 404 page is noindex and answers 404 at its own URL'
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

**Concerns:** `pushword/admin`, `pushword/api`, `pushword/conversation`, `pushword/core`, `pushword/flat`, `pushword/static-generator`

## Dates keep their offset

Exported page files now write `publishedAt` and `holdPublicationAt` with an offset, `'2026-09-30 14:00+00:00'`; a file gains it on its next export. A date written with an offset, in a file or through the API, is now stored at that instant, where `14:00+02:00` used to land at 14:00 server time. Messages and reviews, from `conversation.csv` or the conversation API, too.

**Sites that wrote dates with an offset.** Those pages and messages still hold the shifted time: correct and save them again.

## Dates without an offset follow `editorial_timezone`

A date written without an offset, in a file or through the API, is now read in the new [`editorial_timezone`](/extension/flat#dates-and-time-zones), and the admin shows and edits dates on that clock. Unset, it is the server timezone and nothing changes.

**Sites setting `editorial_timezone`.** Set it last, or a file exported before this release shifts on its next import: `pw:flat:sync` to take in pending file edits, `pw:flat:sync --mode=export --force` to write every date with its offset, then the key.

## The 404 page stays out of the index

The `404` page, and its `<locale>/404` translation, now carries `noindex` whatever its robots field says, leaves the sitemap, the feeds, the search index and `pages_list()`, and answers `404` at its own URL: in the app, in the generated `.htaccess` and `.Caddyfile`, and in [cache mode](/extension/page-cache), which no longer caches it.

**Static sites:** regenerate with `pw:static` to get the new server rules. **Cache-mode sites:** `pw:cache:clear` drops the `404.html` already cached. **Themes overriding the `robots` block:** print `page.metaRobotsContent`, not `page.metaRobots`.
