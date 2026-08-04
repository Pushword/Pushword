---
title: 'full-bleed blocks no longer scroll the page sideways'
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

**Concerns:** `pushword/core`, `@pushword/js-helper`

## A page using `bleed` or `img-stretched` no longer scrolls sideways

**Affects every site, whether or not it uses the horizontal scroller.** Both utilities
are `100vw` wide, and `100vw` counts the classic vertical scrollbar — so on any platform
whose scrollbars take up space (Windows, Linux without overlay scrollbars) they have
always been a scrollbar wider than the content box, giving the page an unwanted
horizontal scroll of exactly that much. macOS and mobile never showed it.

js-helper now clips it away:

```css
body:has(.bleed, .img-stretched) {
  overflow-x: clip;
}
```

`clip` rather than `hidden`: it creates no scroll container, so `position: sticky` inside
keeps working and the vertical axis is untouched. The `:has()` scopes it to pages that
actually bleed.

**Rebuild your assets** to pick it up. If a page of yours deliberately scrolls
horizontally at the page level, override it — `body { overflow-x: visible }` on that
template.

## `wrapperClass` on the `horizontalScroll` view moved to the wrapper

**Only affects sites already passing `wrapperClass` to
`pages_list(…, 'horizontalScroll')`**, which shipped one release ago in rc837.

It used to land on the scrolling `<ul>`. It now lands on the positioned wrapper around
it, because that is what the `::scroll-button()` arrows are laid out against: a layout
class on the row — `bleed` being the obvious one — widened the cards and left the arrows
pinned to the narrow box. The row keeps its own class untouched.

Nothing to do unless you were passing a class meant for the row itself (a `gap-*`, say);
move that into your own CSS on `.horizontal-scroll`.
