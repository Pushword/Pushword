---
title: 'pages_list gets a CSS-only horizontal scroller'
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

**Concerns:** `pushword/admin-block-editor`, `pushword/core`, `@pushword/js-helper`

## `pages_list` has a third view: `horizontalScroll`

**Rebuild your assets after updating** — the component lives in
`@pushword/js-helper`'s `utility.css`, not in a bundle template.

`pages_list('type:blog', 9, 'publishedAt ↓', 'horizontalScroll')` renders the `card`
view's cards in one scrolling row, and the block editor's **format** select offers it
next to `list` and `card`. Nothing changes for existing lists: `list` and `card` render
exactly as before.

The component ships **no JavaScript**. Arrows are `::scroll-button()`, the edge fade is
a `mask-image`, and the end states come from `:disabled`. See
[Pages List](/pages-list) for the two limits worth knowing (the arrow step is not
configurable, and the arrows are Chromium-only — absent elsewhere, by design).

### If you override `component/cardList.html.twig`

The shared card list now takes an `itemClass` variable for the `<li>`:

```twig
<li class="{{ itemClass|default('w-full px-1 my-1 sm:w-1/2 md:w-1/3') }}">
```

Copy that line into your override. Without it the horizontal view still works, but its
cards keep the grid's `sm:w-1/2 md:w-1/3` widths instead of the scroller's fixed ones —
so they render at odd sizes in the row. Overrides of `pages_list_card.html.twig` or
`card.html.twig` are unaffected.
