---
title: 'docker runtime security update; newsletter email designs are reusable; statistics CSV labels unique clickers'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
run: 'doctrine:schema:update --force'
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

**Concerns:** `pushword/core`, `pushword/newsletter`

## Docker runtime security

New Docker builds use [FrankenPHP 1.13.2](https://github.com/php/frankenphp/releases/tag/v1.13.2), with PHP and FrankenPHP security fixes.
**Sites using the Docker skeleton:** merge the updated `Dockerfile`, or run `php bin/console pw:docker:init --force` only if all generated Docker files are uncustomized.
Rebuild and redeploy with `docker compose -f compose.prod.yaml up -d --build`.

## Newsletter email designs

Run `php bin/console doctrine:schema:update --force` once to add campaign design overrides and reusable email templates. Existing audience designs and inherited campaign layouts are preserved. To apply the new site-name header and copyright footer to an existing audience, use **Audiences → Email design → Restore site name and copyright**, then save. See [email design](../extension/newsletter#email-design-and-live-preview).

## Newsletter statistics CSV

Statistics exports now label the per-campaign unique-contact column **Unique clickers** (**Cliqueurs uniques** in French). Update column-name mappings in CSV consumers. See [statistics](../extension/newsletter#statistics).
