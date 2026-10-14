---
title: 'links to another app''s page are absolute outside the default app''s hosts; the admin opens each page where its app runs'
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

**Concerns:** `pushword/admin`, `pushword/core`

## Links to another app's page are absolute outside the default app's hosts

On the host of any app but the first, `page('slug', 'other.host')` now returns the other app's absolute URL, not a `/other.host/slug` path that host answered with a 404. The default app's hosts, and hosts no app declares (`127.0.0.1`), still serve every app under `/{host}/`. Nothing to do.

## The admin opens each page where its app runs

From an admin on the host of any app but the first, the page list's **View** action and the URL under each title, and the edit form's page link, open another app's page on its `base_live_url`, no longer under a `/{host}/` path that answered 404, nor on its `base_url`, where a static export has no drafts.
**Sites with several apps:** a draft shows there only to an editor logged in on that host; the admin on the default app's host previews every app's drafts with one session.
**Sites overriding `PageCrudController::__construct()`:** pass `Pushword\Admin\Service\PageViewUrlGenerator` where `PushwordRouteGenerator` was.
