---
title: 'List Pages as a pro with Pushword CMS'
h1: 'Create Page List<br> <small>Advanced filtering</small>'
publishedAt: '2025-12-21 21:55'
toc: true
---

You can do advanced filtering toward the twig `pages_list` like in the [admin-block-editor](/extension/admin-block-editor).

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
| `slug:`_exampleValue_            | filter pages with the exact slug (useful only with **OR**)                                                      |
| `slug:`_%exampleValue%_          | same than `slug:` with **%** ➜ filter page with the slug containing _exampleValue_.                             |
| `template:`_exampleValue_        | filter pages rendered by this template                                                                          |
| `parent:`_exampleSlug_           | filter pages whose **parent page** has this slug                                                                 |
| `ancestor:`_exampleSlug_         | filter pages sitting under that page, **at any depth** — a whole section in one condition                        |
| `locale:`_exampleValue_          | filter pages of that language (a list is already filtered on the current one)                                    |
| `tag:`_exampleValue_             | filter _tag_ — the explicit form of writing the tag on its own                                                  |
| `prop:`_key_`:`_value_           | filter pages where custom property _key_ equals _value_                                                          |
| `customProperty:`_key_`:`_value_ | the older spelling of `prop:`                                                                                    |
| _exampleValue_                   | filter _tag_ (exact match only !)                                                                               |

Anything the list above does not recognise is a tag search, and always will be:
`type:product` is a perfectly good tag name, and no parser can tell it from a
mistyped prefix. A search matching nothing is therefore silent — run
`pw:pages-list:lint` to find the ones on your site that do.

## Using Operators `OR` or `AND`

Both, in the same query — but never side by side without parentheses. There is no
precedence rule to remember: mixing the two says which comes first, or the search
is refused.

Examples :

- ✔ `related:comment:blog OR related`
- ✔ `parent_children OR related OR page:custom-slug`
- ✔ `parent_children AND related AND page:custom-slug` (this one will output only 1 result)
- ✔ `(parent_children AND related) OR page:custom-slug`
- ✔ `tag:blog AND (tag:featured OR tag:pinned)`
- ✗ `parent_children AND related OR page:custom-slug` ➜ ambiguous, group one side

`AND` and `OR` are recognised as whole uppercase words only, so a tag named
`ORANGE` — or a lowercase `or` — is still ordinary text. A `(` opens a group only
where a term may start: at the beginning, after an operator, or after another
`(`. Everywhere else it is an ordinary character, so a tag written `foo (bar)`
still means what it did before parentheses existed.

## Ordering

The third argument sorts the list: any page column, with an optional direction
(`'weight DESC, publishedAt DESC'`; `↑` and `↓` are accepted), or `prop.<key>` for a
custom property. It defaults to `publishedAt,weight`.

`order: 'search'` is the exception — it keeps the pages in the order their `slug:` terms
are written. A curated row of cards, three pages chosen by hand in that sequence, is what
it is for, and no column can express it:

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

The block editor's PagesList block offers both forms in its order select, and keeps an
order written by hand selected rather than rewriting it.

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

Drop a template at `templates/<host>/component/pages_list_smallCard.html.twig` (any
of the usual template override locations works) and `smallCard` becomes a valid
view name — from Twig calls and from the block editor alike. It receives the same
variables as the built-in views: `pages`, `pager`, `pager_route`,
`pager_route_params`, `id`, `wrapperClass`.

To offer the variant in the block editor's **format** select, declare it on the app:

```yaml
pushword:
    apps:
        - hosts: [example.tld]
          pages_list_displays: [smallCard]
```

A block saved with an undeclared variant keeps working — the select simply shows
the stored name without proposing it elsewhere.

### Changing the card grid's columns

The `card` view's columns live on the wrapper alone
(`grid gap-2 sm:grid-cols-2 md:grid-cols-3`); the items carry no width class. So
`wrapperClass` — or the class tune on the block — replaces the whole layout in one
string, no template override needed:

```twig
{{ pages_list('type:blog', 8, 'publishedAt ↓', 'card', wrapperClass: 'not-prose grid gap-4 sm:grid-cols-2 lg:grid-cols-4 my-5') }}
```

One caveat: classes typed in content only work if they exist in your compiled CSS.
Tailwind generates what it sees in scanned files, so either keep a safelist of the
grid classes you allow, or stick to classes your templates already use.

### The Horizontal Scroller

`horizontalScroll` renders the `card` view's cards inside `.horizontal-scroll`, a
component that carries **no JavaScript at all**. The prev/next arrows are
`::scroll-button()` pseudo-elements, the edge fade is a `mask-image`, and the
"disabled at both ends" behaviour comes from `:disabled` — none of it is computed
in script.

Each edge fades only when something is scrolled past it: at rest the first card is
sharp and only the right edge is faded, and the reverse once you reach the end. That
half is a scroll-driven animation over the mask, guarded by `@supports`; where it is
unsupported both edges stay faded, which is what the plain mask does on its own.

Add `horizontal-scroll-dots` to `wrapperClass` for position dots under the row: every
visible card's dot is filled, half-visible ones half-filled, so clicking the last dot
and seeing nothing move reads as "you are already there". A row **whose cards all fit**
draws none of it — no dots, no fade, and no arrows, since there is no position to
indicate and nothing hidden behind either edge.

The arrows are an enhancement, not the mechanism. They are Chromium-only today, so
elsewhere they are simply absent and the row is scrolled by trackpad, swipe,
shift+wheel or the scrollbar. That is why the scroller is `overflow-x: auto` and
never `overflow-x: hidden`: with `hidden` the arrows become the only way to move,
and every browser without them shows content nobody can reach.

The scrollbar follows the same logic in reverse — it is the fallback affordance and
the only position indicator when there are no arrows, so it is hidden **only** where
`::scroll-button()` is supported:

```css
@supports selector(::scroll-button(inline-end)) {
  .horizontal-scroll { scrollbar-width: none; }
}
```

Two limits worth knowing before you reach for it:

- **The arrow step is not configurable.** The browser scrolls about 85% of the visible
  width — roughly three cards at desktop widths — and its smooth-scroll duration follows
  that distance (~550ms). If the jump feels too big, widen the cards; there is no CSS
  lever for the step itself.
- **The arrows' accessible name comes from CSS**, since a pseudo-element takes no
  `aria-label`. The template sets `--horizontal-scroll-previous` and
  `--horizontal-scroll-next` from the `horizontalScrollPrevious` /
  `horizontalScrollNext` translation keys; override them in your own CSS or
  translations, not in the markup.

`wrapperClass` lands on the **wrapper**, not on the scrolling row — the arrows are
positioned against that wrapper, so a layout class on the row would widen the cards and
leave the arrows pinned to the narrow box. Inside a `prose` column the scroller inherits
its 65ch width; pass `bleed` to break out of it, which also gives the row a gutter so the
first card does not touch the edge of the window:

```twig
{{ pages_list('type:blog', 9, 'publishedAt ↓', 'horizontalScroll', wrapperClass: 'bleed') }}
```

On a site using the block editor, write that call **positionally and fully quoted**
instead — `wrapperClass` is the sixth argument, and the class tune the editor round-trips
to. The block editor's markdown reader only accepts quoted positional arguments, so a
named argument (or a bare `9`) leaves the call as a raw block: it still renders, but it
is no longer editable as a Pages List block.

```twig
{{ pages_list('type:blog', '9', 'publishedAt ↓', 'horizontalScroll', '0', 'bleed') }}
```

Three custom properties theme it:

| Property                        | Default   | Effect                              |
| ------------------------------- | --------- | ----------------------------------- |
| `--horizontal-scroll-fade`      | `2rem`    | width of the edge fade              |
| `--horizontal-scroll-gutter`    | `0`, `1rem` under `bleed` | space before the first card |
| `--horizontal-scroll-thumb`     | `#d1d5db` | scrollbar thumb, where it is shown  |
| `--horizontal-scroll-previous` / `--horizontal-scroll-next` | from translations | the arrows' accessible names |

`--horizontal-scroll-thumb` is worth setting on a dark background. The scrollbar is
given an explicit colour on purpose: left to the platform default, Firefox draws an
overlay scrollbar that only appears while scrolling — invisible exactly where it is
the only affordance.

## Exclude Already Linked Pages

When your page content contains links to other pages, you can exclude those pages from your listings to avoid duplicates. Add the `excludeAlreadyLinked: true` parameter:

```twig
{{ pages_list('taxonomy:travel', 6, excludeAlreadyLinked: true) }}
```

Lists using the parameter also skip what an earlier list on the same page already rendered, so a hub carrying several listings shows each page only once. See [Link Collector](/link-collector).

Or use the `exclude_linked()` function with `pages()`:

```twig
{% set uniquePages = exclude_linked(pages(host, 'taxonomy:travel')) %}
```

See the [Link Collector documentation](/link-collector) for detailed usage, examples, and the full API reference.

## Paginating a List

`max` alone caps a list. Pass `maxPages` as well and the same list is paginated:
`max` becomes the number of cards on **one** pager page, and `maxPages` the number
of pager pages the list may ever have.

```twig
{{ pages_list('type:blog', 12, maxPages: 5) }}
```

That renders 12 cards per page across at most 5 pages — 60 posts in total, the
newest first. The pager itself is rendered by
`component/pager.html.twig`, which extends Pagerfanta's Tailwind view; override it
like any other component to restyle it.

Both numbers matter, and only together:

- `max` is required as soon as `maxPages > 1`. Paginating without a per-page count
  is refused rather than guessed (`"max" (items per page) must be >= 1 when
  paginating with maxPages`).
- `maxPages` is a hard ceiling, not a page count. The query fetches `max × maxPages`
  rows once and Pagerfanta slices them, so raising `maxPages` costs one bigger
  query, not one query per page. Rows beyond that product are never reachable.
- `maxPages: 1` (or 0) means no pagination at all — the list behaves as if only
  `max` were given, and no pager is rendered.

The array form `pages_list('type:blog', [12, 5])` is the older spelling of the same
thing. It still works; passing both an array `max` and `maxPages` is an error.

### Pager URLs

The pager appends the page number as a path segment on the current page:

```
/blog      ← page 1
/blog/2    ← page 2
```

Page 1 is always the bare URL; the route is built from the page currently being
rendered, so a paginated list works on any page, on any host, without configuration.

Two consequences worth knowing before you paginate:

- **`excludeAlreadyLinked` only sees the current pager page.** The other pages are
  never rendered, so they cannot register their cards with the
  [Link Collector](/link-collector).
- **Avoid paginating the homepage.** Its pager URLs are `/2`, `/3`… at the site
  root, which collide with any page whose slug is a bare number, and the page wins —
  the pager silently shows page 1 again. Paginate a section page instead.

## Listing What Is Not Online Yet

`pages_list()` only ever returns pages that are online right now. `draft_list()` takes the
same arguments and renders the same views over the complementary set: pages never scheduled
(no `publishedAt`) and pages scheduled for later. It is the only Twig function that reaches
them as full entities, so an editorial debug page can render real cards — title, main image,
link — instead of the bare slugs `page_uri_list()` returns.

```twig
{{ draft_list('type:blog', 999, 'publishedAt DESC', wrapperClass: 'bg-pink-50 p-4') }}
```

**It renders nothing unless a `ROLE_EDITOR` is logged in** — anonymous visitors get an empty
string, so the block can live on a public page. This is safe against the render cache: the
Markdown cache keys fragments on their post-Twig text, so an editor render and a visitor
render never share a cache entry. Note that `pw:static` generates as an anonymous visitor,
so the block is empty in statically generated HTML.

Two deliberate differences from `pages_list()`:

- noindex pages are **kept** (`pages_list()` drops them) — a draft list that hid them would
  hide exactly the pages you are looking for;
- ordering by `publishedAt` puts never-scheduled pages last, since their `publishedAt` is NULL.

Redirections are excluded, as in `pages_list()`.

## Extending Search with an Event Listener

Before the search string is parsed into DQL criteria, Pushword dispatches a `PagesListSearchEvent`. A listener can inspect or rewrite the string — useful for expanding application-specific prefixes into standard Pushword ones.

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

The listener receives the raw search string before any prefix parsing. Call `setSearch()` to replace it; leave it unchanged to pass through as-is. `getCurrentPage()` provides the current page context if needed.