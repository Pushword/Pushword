---
title: 'card lists and galleries lay themselves out from the wrapper'
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

**Concerns:** `pushword/admin-block-editor`, `pushword/core`, `pushword/link-improver`

## The card list is a grid driven by its wrapper

**Only affects sites that render `pages_list(…, 'card')` or `card_list()` with the
default templates** — a site overriding `component/cardList.html.twig` or
`component/pages_list_card.html.twig` keeps its own layout.

`cardList.html.twig` moved from a centered flex row with width classes on every
item (`sm:w-1/2 md:w-1/3`) to a grid declared on the wrapper alone
(`grid gap-2 sm:grid-cols-2 md:grid-cols-3`); items now carry no class. Column
count and gaps are therefore controlled by `wrapperClass` — or the block editor's
class tune — in one string, without overriding the template.

Two visible differences. An incomplete last row is left-aligned (grid) instead of
centered (flex): pass a flex wrapperClass to get the old look back. And `itemClass`
no longer has a default: if you passed a custom `wrapperClass` while relying on the
default item widths, the items are now cells of your wrapper's grid.

## Galleries default to a centered row, or masonry columns past four images

**Only affects sites that render `{{ gallery(…) }}` without `gridCols` and without
overriding `component/images_gallery.html.twig`.**

The auto-computed square grid (`sm:grid-cols-N`, cropped `aspect-square` images) is
no longer the default. Up to four images sit in one centered wrapping row at their
native ratio (`h-[250px]`); five or more flow into masonry columns
(`columns-2 lg:columns-3`). Passing `gridCols` explicitly keeps the historic grid,
squares included, so `{{ gallery(images, gridCols: '3') }}` is the one-argument way
back per gallery, and overriding `default_gallery_class` restores it site-wide.

If your Tailwind build does not scan `vendor/pushword/core/src/templates`, add the
new classes to your safelist: `columns-2 lg:columns-3 break-inside-avoid h-[250px]
flex-wrap justify-center gap-2 sm:grid-cols-2 md:grid-cols-3`.

## New package: pushword/link-improver — automatic internal linking

New, nothing to do unless you want it. `composer require pushword/link-improver`,
register the bundle after core, then per app set `link_improver: true`: the first
mention of another page's name in rendered content becomes a link to it (each
line of the `name` field is a keyword, line 1 stays the displayed name). Opt-in
because it rewrites rendered content; the source Markdown is never touched.
Inserted links carry `data-auto-link`; audit or preview with
`pw:link-improver [--simulate]`. `link_improver_max_links` (default `0.02`, one
link per 50 words) caps the total of in-content links, existing ones included.
See `/extension/link-improver`.

## Sites can add their own pages_list display variants

New, nothing to do. A bare view name now resolves by convention to your site's
`component/pages_list_<name>.html.twig`, and the app custom property
`pages_list_displays: [<name>, …]` offers it in the block editor's format select.
See `/pages-list`.
