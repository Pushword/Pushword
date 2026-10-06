---
title: 'List Pages as a pro with Pushword CMS'
h1: 'Create Page List<br> <small>Advanced filtering</small>'
publishedAt: '2025-12-21 21:55'
toc: true
---

`pages_list()` (and the [block editor](/extension/admin-block-editor)'s Pages List block) accept a search expression:

| Value                            | Expected behavior                                                                                               |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `children`                       | filter children pages (case insensitive)                                                                        |
| `grandchildren`                  | filter grandchildren pages's pages (case insensitive)                                                           |
| `children_children`              | alias for `grandchildren` (**deprecated**)                                                                      |
| `sisters`                        | filter sister pages (case insensitive)                                                                          |
| `parent_children`                | alias for `sisters` (**deprecated**)                                                                            |
| `related`                        | if there is a parent page, filter results with sister pages with a _pageId_ inferior to _currentPageId_ + **3** |
| `comment:`_exampleValue_         | filter pages containing in _mainContent_ `<!--exampleValue-->` (**deprecated**, prefer tags)                    |
| `related:comment:`_exampleValue_ | same as `related` but instead of _sister pages_, it's pages containing the comment                              |
| `title:`_exampleValue_           | filter pages containing in title (**seo title** or **h1**) the _exampleValue_                                   |
| `content:`_exampleValue_         | same than `title` + searching in _mainContent_ too                                                              |
| `slug:`_exampleValue_            | filter pages with the exact slug (useful only with **OR**); `page:` is an alias                                 |
| `slug:`_%exampleValue%_          | same than `slug:` with **%** ➜ filter page with the slug containing _exampleValue_.                             |
| `template:`_exampleValue_        | filter pages rendered by this template                                                                          |
| `parent:`_exampleSlug_           | filter pages whose **parent page** has this slug                                                                 |
| `ancestor:`_exampleSlug_         | filter pages sitting under that page, **at any depth** — a whole section in one condition                        |
| `locale:`_exampleValue_          | filter pages of that language (a list is already filtered on the current one)                                    |
| `tag:`_exampleValue_             | filter _tag_ — the explicit form of writing the tag on its own                                                  |
| `prop:`_key_`:`_value_           | filter pages where custom property _key_ equals _value_                                                          |
| `customProperty:`_key_`:`_value_ | the older spelling of `prop:`                                                                                    |
| _exampleValue_                   | filter _tag_ (exact match only !)                                                                               |

Anything else is a tag search — `type:product` is a valid tag name, so a mistyped
prefix silently matches nothing. `pw:pages-list:lint` reports the searches on your
site that match no page.

## Using Operators `OR` or `AND`

Both may appear in one query, but mixing them requires parentheses — there is no
precedence rule, so an ungrouped mix is refused.

Examples :

- ✔ `related:comment:blog OR related`
- ✔ `parent_children OR related OR page:custom-slug`
- ✔ `parent_children AND related AND page:custom-slug` (this one will output only 1 result)
- ✔ `(parent_children AND related) OR page:custom-slug`
- ✔ `tag:blog AND (tag:featured OR tag:pinned)`
- ✗ `parent_children AND related OR page:custom-slug` ➜ ambiguous, group one side

`AND` and `OR` are operators only as whole uppercase words (`ORANGE` or `or` stay
text). A `(` opens a group only where a term may start — at the beginning, after an
operator or after another `(` — so a tag written `foo (bar)` is still a tag.

## Ordering

The third argument sorts the list: any page column, with an optional direction
(`'weight DESC, publishedAt DESC'`; `↑` and `↓` are accepted), or `prop.<key>` for a
custom property. It defaults to `publishedAt,weight`.

`order: 'search'` keeps the pages in the order their `slug:` terms are written — for a
hand-picked row of cards:

```twig
{{ pages_list('slug:tour-du-mont-blanc OR slug:gr54 OR slug:vercors', order: 'search', view: 'card') }}
```

- Pages the search matches **without naming** — through another term of the same
  expression — follow the named ones. `slug:tour-du-mont-blanc OR tag:trek` therefore
  pins one card at the head of a tag list.
- **What follows `search` orders that tail**: `order: 'search, weight ↓, publishedAt ↓'`.
  Alone, `search` leaves the default order underneath. `search` must open the
  expression — it sorts the head, so it cannot come second.
- `slug:%partial%` matches more than one page, so it holds no single position. A search
  naming no exact slug simply gets the default order.
- `max` cuts **after** the reordering, so it keeps the ones written first, not the most
  recent ones.

`pages()` takes it too, for a template that arranges the entities itself:

```twig
{% set items = pages(where: 'slug:tour-du-mont-blanc OR slug:gr54', order: 'search') %}
```

The block editor's order select offers both forms and keeps a hand-written order as is.

## Choosing How the List Renders

The fourth argument picks the view. Three are built in; any other bare name is a
**display variant** your site provides by convention, and a value containing `/` or
`.` is taken as a template path.

| Value              | Renders                                                            |
| ------------------ | ------------------------------------------------------------------ |
| `list` (or empty)  | a plain `<ul>` of links — `component/pages_list.html.twig`          |
| `card`             | the card grid — `component/pages_list_card.html.twig`               |
| `horizontalScroll` | the same cards in one scrolling row — `component/pages_list_horizontal.html.twig` |
| any other bare name | your site's `component/pages_list_<name>.html.twig`               |

```twig
{{ pages_list('type:blog', 9, 'publishedAt ↓', 'horizontalScroll') }}
```

All three are available from the block editor's **format** select.

### Site display variants

A template at `templates/<host>/component/pages_list_smallCard.html.twig` (or any
other [override location](/override-theme)) makes `smallCard` a valid view name, in
Twig and in the block editor. It receives the built-in views' variables: `pages`,
`pager`, `pager_route`, `pager_route_params`, `id`, `wrapperClass`.

To offer the variant in the block editor's **format** select, declare it on the app:

```yaml
pushword:
    apps:
        - hosts: [example.tld]
          pages_list_displays: [smallCard]
```

A block saved with an undeclared variant keeps working; the select shows the stored name.

### Changing the card grid's columns

The `card` view's columns live on the wrapper alone
(`grid gap-2 sm:grid-cols-2 md:grid-cols-3`), so `wrapperClass` — or the block's class
tune — replaces the layout without a template override:

```twig
{{ pages_list('type:blog', 8, 'publishedAt ↓', 'card', wrapperClass: 'not-prose grid gap-4 sm:grid-cols-2 lg:grid-cols-4 my-5') }}
```

Classes typed in content only work if they exist in your compiled CSS: safelist the
grid classes you allow, or reuse classes your templates already contain.

### The Horizontal Scroller

`horizontalScroll` renders the `card` view's cards inside `.horizontal-scroll`, with
**no JavaScript**: the prev/next arrows are `::scroll-button()` pseudo-elements, the
edge fade is a `mask-image`, and disabled arrows come from `:disabled`.

An edge fades only when content is scrolled past it (a scroll-driven animation guarded
by `@supports`; unsupported browsers fade both edges). Add `horizontal-scroll-dots` to
`wrapperClass` for position dots — visible cards filled, half-visible half-filled. A row
whose cards all fit draws no dots, fade or arrows.

The arrows are Chromium-only; elsewhere the row scrolls by trackpad, swipe, shift+wheel
or scrollbar. Keep the scroller `overflow-x: auto`, never `hidden`, or browsers without
arrows cannot reach the content. For the same reason the scrollbar is hidden **only**
where `::scroll-button()` is supported:

```css
@supports selector(::scroll-button(inline-end)) {
  .horizontal-scroll { scrollbar-width: none; }
}
```

Limits:

- **The arrow step is not configurable.** The browser scrolls about 85% of the visible
  width (~550ms). If the jump feels too big, widen the cards.
- **The arrows' accessible name comes from CSS**, since a pseudo-element takes no
  `aria-label`. The template sets `--horizontal-scroll-previous` and
  `--horizontal-scroll-next` from the `horizontalScrollPrevious` /
  `horizontalScrollNext` translation keys; override them in your own CSS or
  translations, not in the markup.

`wrapperClass` lands on the **wrapper** (the arrows' positioning box), not on the
scrolling row. Inside a `prose` column the scroller inherits its 65ch width; pass `bleed`
to break out of it, with a gutter before the first card:

```twig
{{ pages_list('type:blog', 9, 'publishedAt ↓', 'horizontalScroll', wrapperClass: 'bleed') }}
```

With the block editor, write the call **positionally and fully quoted** (`wrapperClass`
is the sixth argument). A named argument or a bare `9` still renders, but the editor
keeps the call as a raw block instead of a Pages List block.

```twig
{{ pages_list('type:blog', '9', 'publishedAt ↓', 'horizontalScroll', '0', 'bleed') }}
```

Custom properties:

| Property                        | Default   | Effect                              |
| ------------------------------- | --------- | ----------------------------------- |
| `--horizontal-scroll-fade`      | `2rem`    | width of the edge fade              |
| `--horizontal-scroll-gutter`    | `0`, `1rem` under `bleed` | space before the first card |
| `--horizontal-scroll-thumb`     | `#d1d5db` | scrollbar thumb, where it is shown  |
| `--horizontal-scroll-previous` / `--horizontal-scroll-next` | from translations | the arrows' accessible names |

Set `--horizontal-scroll-thumb` on a dark background. The explicit colour stops
Firefox from drawing an overlay scrollbar that only appears while scrolling.

## Exclude Already Linked Pages

`excludeAlreadyLinked: true` drops pages the content already links to:

```twig
{{ pages_list('taxonomy:travel', 6, excludeAlreadyLinked: true) }}
```

It also skips pages an earlier list on the same page rendered, so a hub with several
lists shows each page once. With `pages()`, use `exclude_linked()`:

```twig
{% set uniquePages = exclude_linked(pages(host, 'taxonomy:travel')) %}
```

Details: [Link Collector](/link-collector).

## Paginating a List

`max` alone caps a list. Pass `maxPages` as well and the same list is paginated:
`max` becomes the number of cards on **one** pager page, and `maxPages` the number
of pager pages the list may ever have.

```twig
{{ pages_list('type:blog', 12, maxPages: 5) }}
```

12 cards per page across at most 5 pages — 60 posts, newest first. The pager is
`component/pager.html.twig` (extends Pagerfanta's Tailwind view); override it to restyle.

- `max` is required as soon as `maxPages > 1` (`"max" (items per page) must be >= 1
  when paginating with maxPages`).
- `maxPages` is a ceiling: one query fetches `max × maxPages` rows and Pagerfanta slices
  them. Rows beyond that product are unreachable.
- `maxPages: 1` (or 0) disables pagination: no pager is rendered.

The legacy array form `pages_list('type:blog', [12, 5])` still works; combining it
with `maxPages` is an error.

### Pager URLs

The pager appends the page number as a path segment on the current page:

```
/blog      ← page 1
/blog/2    ← page 2
```

Page 1 is the bare URL. The route is built from the current page, so pagination works
on any page and host without configuration. Caveats:

- **`excludeAlreadyLinked` only sees the current pager page.** The other pages are
  never rendered, so they cannot register their cards with the
  [Link Collector](/link-collector).
- **Avoid paginating the homepage.** Its pager URLs are `/2`, `/3`… at the site
  root, which collide with any page whose slug is a bare number, and the page wins —
  the pager silently shows page 1 again. Paginate a section page instead.

## Listing What Is Not Online Yet

`pages_list()` only returns pages online now. `draft_list()` takes the same arguments
and views over the complement: pages with no `publishedAt` or scheduled for later — as
full entities (cards with title and image), unlike the bare slugs of `page_uri_list()`.

```twig
{{ draft_list('type:blog', 999, 'publishedAt DESC', wrapperClass: 'bg-pink-50 p-4') }}
```

**It renders nothing unless a `ROLE_EDITOR` is logged in**, so it can live on a public
page; the Markdown cache keys on post-Twig text, so editor and visitor renders never share
an entry. `pw:static` renders as a visitor, so static HTML gets nothing.

Differences from `pages_list()`:

- noindex pages are **kept** (`pages_list()` drops them) — a draft list that hid them would
  hide exactly the pages you are looking for;
- ordering by `publishedAt` puts never-scheduled pages last, since their `publishedAt` is NULL.

Redirections are excluded, as in `pages_list()`.

## Extending Search with an Event Listener

Before parsing the search string, Pushword dispatches a `PagesListSearchEvent`. A listener can rewrite it — e.g. to expand an app-specific prefix into standard ones.

**Event:** `Pushword\Core\Event\PagesListSearchEvent`  
**Constant:** `PushwordEvents::PAGES_LIST_SEARCH` (`pushword.pages_list.before_search`)  
**Dispatched by:** `pages_list()` and `pages()` Twig functions

```php
use Pushword\Core\Event\PagesListSearchEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PagesListSearchEvent::NAME)]
final readonly class ProductSearchListener
{
    public function __invoke(PagesListSearchEvent $event): void
    {
        // The raw search, before parsing — so a rewrite must stop at the
        // delimiters. A `\S+` here would swallow a closing parenthesis and turn
        // "(product:A OR product:B)" into a term ending in ")".
        $event->setSearch(preg_replace(
            '/product:([^\s)]+)/',
            'prop:productCode:$1',
            $event->getSearch(),
        ) ?? $event->getSearch());
    }
}
```

`setSearch()` replaces the string; `getCurrentPage()` gives the current page.