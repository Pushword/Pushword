---
title: 'conversation notification templates can be overridden; selected messages support delayed delivery'
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

**Concerns:** `pushword/conversation`

## Conversation notification templates

Individual subjects, HTML and plain text now use [overridable Twig templates](/extension/conversation#customize-individual-notifications); visitor content is escaped in HTML.
**Sites customizing conversation templates:** Symfony overrides in `templates/bundles/PushwordConversationBundle/` now take priority over bundled templates. Review existing overrides, then clear the cache in each environment used (for example `cache:clear --env=prod` and `cache:clear --env=test`).

## Delayed conversation notifications

Update the packages, then run `doctrine:schema:update --force` to add the notification queue fields and index.
**Sites using `notificationDelay` (for example `600` seconds / ten minutes):** run `pw:conversation:notify-pending` every minute, clear the application/test caches, restart the scheduler worker if used, and run the tests before enabling delivery; see the [integration steps](/extension/conversation#delay-one-message-notification).
The worker must keep running even when a visitor abandons the optional form step; no Messenger transport or HTTP sleep is required, and later updates after delivery send no second email.
