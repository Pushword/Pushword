---
title: 'newsletter site filters, planning and preflight; sender profiles and reusable blocks; first-name placeholders'
run: 'doctrine:schema:update --force'
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

**Concerns:** `pushword/newsletter`

## Campaign site metadata

**External campaign catalogue integrations:** optionally supply `hosts: ['example.org']` on each `CampaignIndexItem`; omitted hosts display as unknown. Set `newsletter.catalogue_default_host` only to select an initial site. Reset still shows all sites. See [the contract](../extension/newsletter#multi-source-campaign-index).

## First-name placeholders

`%firstName%` and `%firstName|fallback%` now resolve in newsletter content. **Sites already using these strings literally:** adjust that text. To personalize, map a string into `customProperties.firstName`; missing values use the authored fallback or an empty string. No schema update is required.

## Newsletter preparation and sending

Run `php bin/console doctrine:schema:update --force` for planning fields, sender profiles, saved issues and reusable blocks. **Campaign integrations:** Send (admin/API/CLI) now honors a future `scheduledAt`; clear it for immediate sending. Cancelling keeps the date as a draft plan. Update custom admin actions that offered Schedule to use Send. See [editorial planning](../extension/newsletter#editorial-planning-and-calendar).

## Newsletter preflight and reusable blocks

**Campaign API clients:** handle HTTP 422 and its `issues` on send/schedule. Checks include audience, subject, body, links, sender and blocks at the requested date. A blocked campaign returns to draft and needs explicit Send after correction. See [preflight and extension hooks](../extension/newsletter#campaign-preflight).

## Personal email consent

**Sites using the personal email layout:** it now preserves marketing unsubscribe links/headers and the postal address. Use the explicit transactional flag only for service messages. Update custom `personal.email.html.twig` overrides to render `unsubscribeUrl` and `postalAddress`. Campaign delivery fields are now read-only after authorization; cancel scheduling before editing.
