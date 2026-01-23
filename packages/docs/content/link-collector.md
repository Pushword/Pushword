---
title: 'Avoid Duplicate Links in Page Listings'
h1: 'Link Collector<br> <small>Prevent duplicate page links</small>'
publishedAt: '2026-01-23'
toc: true
---

When building listing-heavy sites (travel agencies, blogs with related posts, e-commerce), the same page link often appears multiple times: once in your markdown content and again in automated listings like `pages_list()`. This creates a poor UX with redundant links.

The **LinkCollector** feature tracks which pages are already linked in your content, allowing you to exclude them from automated listings.

## How It Works

The LinkCollector scans your page content **before** Twig/Markdown processing and collects all internal link slugs. These slugs are then available to filter out from your page listings.

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

The simplest way to use this feature is with the `excludeAlreadyLinked` parameter:

```twig
{{ pages_list('children', 6, excludeAlreadyLinked: true) }}
```

This automatically excludes any pages that are already linked in your content.

### Full Example

```twig
{# Your page content contains links like [See our hiking tours](/hiking) #}

<h2>Related Products</h2>
{{ pages_list('taxonomy:outdoor', 4, excludeAlreadyLinked: true) }}
{# hiking page won't appear here since it's already linked above #}
```

## Usage with pages() Function

For more control, use the `exclude_linked()` function with `pages()`:

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

If you create custom Twig functions that return page lists, inject the `LinkCollectorService`:

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

## Real-World Examples

### Travel Agency Site

```twig
{# Page content mentions specific destinations #}
Discover our [Paris tours](/destinations/paris) and
[Rome adventures](/destinations/rome).

{# Sidebar shows other destinations, excluding mentioned ones #}
<aside>
  <h3>Other Destinations</h3>
  {{ pages_list('taxonomy:destination', 5, excludeAlreadyLinked: true) }}
</aside>
```

### Blog with Related Posts

```twig
{# Article references other posts inline #}
As mentioned in [Getting Started](/blog/getting-started)...

{# Related posts section excludes referenced articles #}
<section class="related">
  <h2>You might also like</h2>
  {{ pages_list('sisters', 3, excludeAlreadyLinked: true) }}
</section>
```

### E-commerce Product Page

```twig
{# Product description links to related products #}
This pairs well with our [Premium Case](/accessories/premium-case).

{# Recommended products excludes already-mentioned items #}
<div class="recommendations">
  {% set recommendations = exclude_linked(pages(host, 'taxonomy:accessory')) %}
  {% for product in recommendations|slice(0, 4) %}
    {% include '/component/product_card.html.twig' with {page: product} %}
  {% endfor %}
</div>
```

## Performance Notes

- The LinkCollector filter runs **once** per page render, before Twig processing
- Link detection uses optimized regex patterns with minimal overhead
- The collector is automatically reset on each HTTP request
- No database queries are made for link collection

## Backward Compatibility

This feature is enabled by default but has **zero impact** on existing sites:
- The filter always runs but only collects links passively
- No content is modified
- Filtering only happens when you explicitly use `excludeAlreadyLinked: true` or `exclude_linked()`
