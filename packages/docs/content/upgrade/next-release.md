---
title: 'a mailbox of delivery failures can be read back into the list'
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

- `title:` — the "What changed" cell of the index table. One line, lower case,
  written from the site's side ("the newsletter form is fetched, and CSRF-protected")
  rather than the diff's ("refactor NewsletterFormController"). Required as soon as
  the note has a section; the release stops if it is still empty.
- `run:` — the command(s) the release expects, without `php bin/console`. Omit the
  key when there is none. A list runs in the order given.
- `**Concerns:**` — first line of the body, listing every package a site has to
  install to be affected. Alphabetical, full composer names, `@pushword/js-helper`
  last. Add the packages your change touches to the line, keep the others.
- One `##` section per change, saying what breaks and what to do about it.

Several changes land here between two tags: append to the file, do not replace it.
-->

**Concerns:** `pushword/newsletter`

## Addresses that bounce can be taken off the list

Until now a bounce was only recorded when the transport refused the mail during
the send itself. The common case is the other one: the relay accepts the message,
the remote server refuses it a minute later, and the failure comes back as a
separate mail to the **envelope sender**, which is a different address from the
`From:` a reader sees. Nothing read it, so a dead address stayed subscribed and
every campaign retried it, which is how a sending reputation is spent.

`pw:newsletter:bounces` reads the mailbox those failures land in. Nothing changes
for a site that does not configure one.

Point the envelope at a mailbox nobody reads by hand, and say where it lives:

```yaml
# config/packages/mailer.yaml
framework:
  mailer:
    envelope:
      sender: bounce@example.com

# config/packages/pushword.yaml
newsletter:
  bounce_maildir: /home/user/mail/example.com/bounce
```

Then read it from cron, next to the tick:

```cron
0,15,30,45 * * * * cd /path/to/app && bin/console pw:newsletter:bounces -q
```

Start with `--dry-run`, which says what it would drop without touching the
database or the mailbox.

On a shared host this needs nothing else: a bounce is a file, delivered next to
every other mailbox, so there is no IMAP extension to compile, no webhook to
expose and no credentials to store. A mailbox that only exists on a remote IMAP
server is out of scope.

Three things worth knowing before pointing it at a live mailbox:

- it acts on permanent failures only (`Status: 5.x.x`). A 4.x.x is a mailbox that
  was full an hour ago, and dropping a reader over one loses an address the next
  retry reaches,
- an address held on several audiences leaves all of them, since the server
  refused the address and not one of the lists,
- everything it reads is moved to `cur/` with the seen flag, including the mail
  that was not a bounce at all. The same mailbox collects the delivery failures
  of every other mail the app sends, and those are counted and reported without
  anybody being touched.

The envelope sender is global to the app, so check the domain before setting it:
SPF authenticates the envelope, and DMARC wants it aligned with the `From:`
domain. A bounce address on the same domain keeps that alignment; one on another
domain leaves it resting on DKIM alone.
