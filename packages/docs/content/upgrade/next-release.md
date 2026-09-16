---
title: 'page list row actions moved into a dropdown; the hold switch became a column and `pw_page_holdable()` is gone; the admin has a type scale, corner-radius and elevation tokens, and `<small>` no longer compounds; the media list opens in mosaic and remembers the view you picked'
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

**Concerns:** `pushword/admin`

## Page list row actions moved into a dropdown

Each row of the page list now shows a single `⋯` menu instead of a row of Edit / Clone /
Delete buttons, and page titles are no longer rendered in the link colour.

**Affects sites that override `@pwAdmin/page/index.html.twig` or style
`.pw-page-actions > a` / `.pw-page-actions > form > button`.** Those selectors are gone;
target `.pw-page-actions .dropdown-menu .dropdown-item` instead. A custom page-list action
that posts through `@pwAdmin/crud/action_post.html.twig` must now also call
`->renderAsForm()`, or it renders through EasyAdmin's token-less menu item and its CSRF
check rejects the request.

## The hold switch is a page list column

"Hold publication" left the title cell for a sortable column of its own, between Title
and Weight, and the list gained a "View" row action. Nothing to do.

**Affects templates calling `pw_page_holdable()`.** That Twig function is removed — it
ignored its `$host` argument and only reported whether `pushword/static-generator` was
installed. Test for the bundle directly, or drop the call.

## The admin CSS has a type scale

Admin font sizes come from five custom properties — `--pw-text-xs` (12px), `--pw-text-sm`
(14px), `--pw-text-md` (16px), `--pw-text-lg` (20px) and `--pw-text-xl` (28px) — instead
of a mix of `rem`, `px`, `em` and `%` that computed to values like 11.375px and 15.2px.

**Affects sites that style admin text or rely on `<small>` inside the admin.** `small` and
`.small` are now pinned to `--pw-text-xs` rather than Bootstrap's `0.875em`, so they no
longer shrink relative to their container. Use the custom properties in your own admin CSS.

## Corner radius and elevation are tokens too

`--pw-radius-sm|md|lg` (4/8/12px) replace the eight radii the admin used to declare, and
`--pw-elevation-1|2|3|4` replace ten ad-hoc shadows written in three notations. Nothing to
do — reuse the properties instead of hard-coding values in your own admin CSS.

## The media list opens in mosaic and remembers your choice

The media index now defaults to the mosaic layout, and an explicit switch between List and
Mosaic sticks for the rest of the session instead of resetting on the next visit.

**Affects links and tests that opened the media list expecting the table.** A URL without a
`view` parameter now renders the mosaic; pass `?view=table` to force the table layout.
