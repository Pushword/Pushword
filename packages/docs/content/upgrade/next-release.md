---
title: 'a background task waiting in a messenger queue reports `queued`, no longer `completed`; background commands can be routed to their own transport'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

**Concerns:** pushword/core, pushword/page-scanner, pushword/static-generator

## `queued` is a new status

Only with `background_task_handler: messenger`. A generation or scan dispatched but not yet picked up by a consumer was reported as `completed` — by the admin screens and by the `status` field of `GET /api/static/{host}` and `GET /api/page-scan`. It now reports `queued`.

**Affected:** any client polling those endpoints. Treat `queued` like `running` — the pass has not started, keep waiting. `POST` on both endpoints now answers with the real starting status too, so a client learns it is queued without polling.

## Route a command to its own transport

Optional. Every background task shares one message class, so one transport serves them first-come and a mass sweep can queue in front of an interactive task. Name a transport per command to give it its own lane — see [Background Tasks](/background-tasks).

```yaml
pushword:
    background_task_transports:
        'pw:static': static
```

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
