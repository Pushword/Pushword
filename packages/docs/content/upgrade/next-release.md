---
title: 'admin mutations require POST and CSRF; uploads are allow-listed; static quiz results are signed; HTTP headers are hardened'
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

**Concerns:** `pushword/admin`, `pushword/admin-block-editor`, `pushword/api`, `pushword/conversation`, `pushword/core`, `pushword/newsletter`, `pushword/quiz`, `pushword/static-generator`, `pushword/version`, `@pushword/js-helper`

## Regenerate static pages containing quizzes

Anonymous quiz-result writes now require the per-page signature embedded in rendered HTML.
**Affects statically published sites using `pushword/quiz`.** Run `pw:static` after updating.

## Submit custom administrative actions with CSRF

Page clone/promote, media rotate, newsletter contact/campaign mutations and version restore/reset now accept POST only and validate `_token`.
**Affects sites linking to those routes from custom admin templates or tools.** Replace links with POST forms and use the token identifiers rendered by the bundled templates.

## Check custom upload formats

Interactive media uploads now accept only AVIF, CR2, CSV, GIF, GPX, JPEG, MOV, MP4, PDF, PNG, SVG, TXT, WebM, WebP and ZIP with matching detected content types.
**Affects sites uploading another format.** Convert it to an allowed format before uploading.

## Carry the hardened web-server headers

Generated static Caddy and Apache configurations now include CSP, HSTS and Permissions-Policy while allowing forms to reach `base_live_url`.
**Affects sites maintaining their own root Caddyfile.** Copy the security headers from `vendor/pushword/dev-app/Caddyfile`; set `CADDY_LIVE_ORIGIN` when forms post to a separate live origin.
