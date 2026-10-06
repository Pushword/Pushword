---
title: 'Avoid Duplicate Links in Page Listings'
h1: 'Link Collector<br> <small>Prevent duplicate page links</small>'
publishedAt: '2026-01-23 00:00'
toc: true
---

A page often links the same target twice: once in its content, again in a `pages_list()`. The **LinkCollector** records the internal links of the content so listings can exclude them.

## How It Works

The LinkCollector scans the page content **before** Twig/Markdown processing and collects every internal link slug.

```
Raw Content:
  "Check out [our Alps tours](/alps/hiking) and..."

  ↓ LinkCollector scans content
  → Finds: "/alps/hiking"
  → Registers slug in collector

  ↓ Twig executes (pages_list, custom functions)
  → Can now exclude "alps/hiking" from results

  ↓ Final output
  → No duplicate links
```

## Supported Link Formats

The collector detects internal links in these formats:

| Format | Example |
|--------|---------|
| Markdown links | `[text](/my-page)` |
| Markdown with anchor | `[text](/my-page#section)` |
| Markdown with query | `[text](/my-page?ref=home)` |
| HTML href (double quotes) | `<a href="/my-page">` |
| HTML href (single quotes) | `<a href='/my-page'>` |
| Nested slugs | `[text](/category/subcategory/page)` |

**Not collected:**
- External links (`https://example.com`)
- Relative links (`relative-page` without leading `/`)
- Anchor-only links (`#section`)

## Usage with pages_list

```twig
{# Content contains [See our hiking tours](/hiking): the hiking page is left out #}
{{ pages_list('children', 6, excludeAlreadyLinked: true) }}
```

A list using the parameter also **registers the cards it renders**, so several lists on the same page never show the same page twice:

```twig
{{ pages_list('taxonomy:destination', 4, excludeAlreadyLinked: true) }}
{# a card rendered above will not come back below #}
{{ pages_list('children', 6, excludeAlreadyLinked: true) }}
```

Registering is opt-in, like filtering: a `pages_list()` **without** the parameter renders normally and leaves the collector untouched — a full listing followed by an excluding carousel keeps working.

An excluding list still renders its full `max`: the exclusion is done by the query, so `max` counts the pages that survived it — the list backfills with the next matching pages instead of coming up short.

A paginated list only registers the cards of the pager page it renders; the other pager pages are not linked, so they stay listable.

## Usage with pages() Function

For more control, filter `pages()` with `exclude_linked()`:

```twig
{% set allPages = pages(host, 'taxonomy:travel') %}
{% set uniquePages = exclude_linked(allPages) %}

<ul>
{% for page in uniquePages %}
  <li><a href="{{ pw_path(page) }}">{{ page.title }}</a></li>
{% endfor %}
</ul>
```

## Available Twig Functions

| Function | Description | Return Type |
|----------|-------------|-------------|
| `exclude_linked(pages)` | Filter out already-linked pages from an array | `Page[]` |
| `is_slug_linked(slug)` | Check if a specific slug was linked in content | `bool` |
| `linked_slugs()` | Get all slugs that were linked (for debugging) | `string[]` |

### Checking Individual Slugs

```twig
{% for page in pages(host, 'related') %}
  {% if not is_slug_linked(page.slug) %}
    <a href="{{ pw_path(page) }}">{{ page.title }}</a>
  {% endif %}
{% endfor %}
```

### Debugging Collected Links

```twig
{# See what links were collected from your content #}
{{ dump(linked_slugs()) }}
{# Output: ['alps/hiking', 'beach-trips', 'contact'] #}
```

## Usage in Custom Twig Functions (PHP)

A custom Twig function returning pages can inject `LinkCollectorService`:

```php
use Pushword\Core\Service\LinkCollectorService;

final class MyExtension
{
    public function __construct(
        private readonly LinkCollectorService $linkCollector,
    ) {
    }

    #[AsTwigFunction('my_product_list', isSafe: ['html'])]
    public function renderProductList(): string
    {
        $pages = $this->getProductPages();

        // Exclude pages already linked in content
        $pages = $this->linkCollector->excludeRegistered($pages);

        // Render...
    }
}
```

### LinkCollectorService API

```php
// Register a slug manually
$linkCollector->registerSlug('my-page');

// Register from a Page entity
$linkCollector->register($page);

// Check if a slug is registered
$linkCollector->isSlugRegistered('my-page'); // true/false

// Filter an array of pages
$uniquePages = $linkCollector->excludeRegistered($pages);

// Get all registered slugs
$slugs = $linkCollector->getRegisteredSlugs(); // ['slug' => true, ...]
```

## Runtime

- The filter runs **once** per page render, before Twig, without database queries; excluding lists then add their own rendered cards as Twig executes.
- The collector resets on each HTTP request and between two pages of a static export.
- It only collects: content is never modified, and nothing is filtered unless you use `excludeAlreadyLinked: true` or `exclude_linked()`.