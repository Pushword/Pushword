---
title: 'production credentials are one-time; authenticated, generated and network surfaces are hardened'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
run: 'pw:image:cache'
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

**Concerns:** `pushword/admin`, `pushword/core`, `pushword/dev-app`, `pushword/flat`, `pushword/page-scanner`, `pushword/repurpose`, `pushword/static-generator`

## Merge the production Docker hardening

New Docker files disable PHP version disclosure and generate a forced-change random
password when no production administrator password is configured. Sites that already
copied the Docker skeleton should merge `docker/php.prod.ini` and
`docker/docker-entrypoint.sh`, or rerun `pw:docker:init --force` only if those files
have not been customised.

## Account and endpoint behaviour is stricter

Remember-me now follows its checkbox, drafts require an editor session, flat lock APIs
require `ROLE_EDITOR`, and authenticated pages are not browser-cacheable. Cheatsheet
and Repurpose creation links now open a POST confirmation. No site change is required.

## Generated paths and external links are confined

Page-generated files cannot escape their configured directories, SVG responses are
sandboxed, and external link checks block private networks while verifying TLS. Review
the [security model](/security) before granting editorial access. Run `pw:image:cache`
once to remove legacy public SVG symlinks.
