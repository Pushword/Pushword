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
| `customProperty:`_key_`:`_value_ | filter pages where custom property _key_ equals _value_                                                         |
| _exampleValue_                   | filter _tag_ (exact match only !)                                                                               |

## Using Operators `OR` or `AND`

For now, you can only use `OR` or `AND` operator in the same query

Examples :

- ✔ `related:comment:blog OR related`
- ✔ `parent_children OR related OR page:custom-slug`
- ✔ `parent_children AND related AND page:custom-slug` (this one will output only 1 result)
- ✗ `parent_children AND related OR page:custom-slug` (this one will output only 1 result)
- ✗ `(parent_children AND related) OR page:custom-slug` (this one will output only 1 result)

## Exclude Already Linked Pages

When your page content contains links to other pages, you can exclude those pages from your listings to avoid duplicates. Add the `excludeAlreadyLinked: true` parameter:

```twig
{{ pages_list('taxonomy:travel', 6, excludeAlreadyLinked: true) }}
```

Or use the `exclude_linked()` function with `pages()`:

```twig
{% set uniquePages = exclude_linked(pages(host, 'taxonomy:travel')) %}
```

See the [Link Collector documentation](/link-collector) for detailed usage, examples, and the full API reference.

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
        $search = $event->getSearch();
        // e.g. expand "product:NLDLV0019" into "customProperty:productCode:NLDLV0019"
        if (str_starts_with($search, 'product:')) {
            $event->setSearch('customProperty:productCode:' . substr($search, 8));
        }
    }
}
```

The listener receives the raw search string before any prefix parsing. Call `setSearch()` to replace it; leave it unchanged to pass through as-is. `getCurrentPage()` provides the current page context if needed.