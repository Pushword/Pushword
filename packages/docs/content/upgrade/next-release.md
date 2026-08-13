---
title: 'a newsletter audience, campaign or automation can be marked transactional and sent with no unsubscribe link; the static Caddyfile webp fallback needs a rebuild to fire reliably; render typography keeps quote pairs straight and a number with its unit'
run: 'doctrine:schema:update --force'
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

**Concerns:** `pushword/admin-block-editor`, `pushword/core`, `pushword/flat`, `pushword/newsletter`, `pushword/page-scanner`, `pushword/static-generator`

## Transactional mail: no unsubscribe link, no `List-Unsubscribe`

An audience, a campaign and an automation each carry a **Transactional** flag,
off by default, which sends their mail with no unsubscribe link in either part of
the body and no `List-Unsubscribe` headers — for service messages only, see
[the newsletter doc](/extension/newsletter#mail-with-no-way-out). Nothing changes
for existing mail; the three new columns need
`php bin/console doctrine:schema:update --force`.

## The Caddy webp fallback no longer depends on matcher evaluation order

The generated Caddyfile probes the original-image candidates from a `try_files`
directive instead of a sibling of the `path_regexp` matcher, which Caddy evaluates
in non-deterministic order — the fallback could be silently inert for a whole
process. **Caddy-served static sites**: regenerate the build, reload Caddy.

## Render typography: quote pairs, units, inline scripts

A straight single-quote pair stays fully straight (only `letter'letter` curls, as
the editor rule always had it), a number and its unit or currency keep a no-break
space again, and typography survives an inline `<script>`/`<style>`/`<textarea>`
holding a glued `<`. Rendered output changes on unchanged content; nothing to do.

## Code samples survive export byte-identical

Source normalization (straightening quotes, ellipses, no-break spaces) no longer
reaches fenced blocks and inline code on flat export or editor save. Nothing to do.

## Derivative checks and worker kills do what they said

`pw:page-scan` probes derivatives where `pw:image:cache` writes them
(`pw.media_cache_dir`, not `public_dir/media`) — constructing `LinkedDocsScanner`
by hand needs the new `$mediaCacheDir` argument; DI sites have nothing to do. And
the 300-second idle worker kill rc874 announced is now actually enforced.
