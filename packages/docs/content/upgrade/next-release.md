---
title: 'audiences carry a postal address, printed at the foot of every mail; confirmation mails are capped per address, so resubmitting no longer resends at once'
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

**Concerns:** `pushword/newsletter`

## Audiences carry a postal address

An optional address on the audience, printed at the foot of every mail it sends,
in both the HTML and the text part. **Concerns sites sending commercial mail:**
US law (CAN-SPAM) asks for one, so fill it in before the next send — Newsletter →
Audiences → Sender. A site calling `MailRenderer::text()` itself now passes the
audience as its first argument.

## Confirmation mails are capped per address

The subscribe endpoint sends at most one confirmation per address and audience
every ten minutes, and five a day: the per-IP ceiling could not see a
list-bombing spread over a botnet. Resubmitting inside the window answers exactly
as before and sends nothing. Nothing to run.
