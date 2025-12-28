---
title: 'Entity Filter - Transform Page properties with filters'
h1: 'Entity Filter'
id: 20
publishedAt: '2025-12-21 21:55'
parentPage: search.json
name: 'Entity Filter'
filter_twig: 0
---

The **Entity Filter** is one of the most powerful components in Pushword. It allows you to apply transformations on entity properties (mostly `Page`) through a flexible and extensible filter system.

## Usage

Filters can be used from **PHP** via `Pushword\Core\Component\EntityFilter\Manager` or directly in **Twig** templates with the `pw` function:

```twig
{# Render markdown in title #}
{{ pw(page).title|raw }}

{# Get processed main content (returns string) #}
{{ pw(page).mainContent|raw }}

{# Split content into parts (chapeau, intro, toc, etc.) #}
{% set mainContent = mainContentSplit(page) %}
{{ mainContent.chapeau|raw }}
{{ mainContent.body|raw }}
```

For a title like `Page **title**`, this will render `Page <strong>Title</strong>` if the markdown filter is configured for `title` in `pushword.filters`.

## Configuration

Filters are configured per property in your Pushword configuration:

```yaml
# config/packages/pushword.yaml
pushword:
    filters:
        title: [twig, markdown]
        h1: [twig, elseH1]
        mainContent: [twig, markdown]
```

## Disabling Filters Per Page

Each page can override filters by setting `filter_*filterName*: false` in `customProperties`. For example, [this documentation page](https://github.com/Pushword/Pushword/tree/main/packages/docs/content/component/entity-filter.md) has twig disabled with `filter_twig: 0`.

## Built-in Filters

Available filters are located in #[core/Component/EntityFilter/Filter](https://github.com/Pushword/Pushword/tree/main/packages/core/src/Component/EntityFilter/Filter):

| Filter | Description |
|--------|-------------|
| `date` | Converts date shortcodes to formatted dates |
| `elseH1` | Falls back to H1 if property is empty |
| `extended` | Inherits value from extended page if empty |
| `htmlLinkMultisite` | Resolves internal links for multisite setups |
| `htmlObfuscateLink` | Obfuscates external links |
| `markdown` | Converts markdown to HTML |
| `name` | Extracts first line as name |
| `showMore` | Handles show-more content sections |
| `twig` | Renders Twig expressions in content |

## Content Splitting with mainContentSplit()

For templates that need to split content into parts (chapeau, intro, toc, etc.), use the `mainContentSplit(page)` Twig function:

```twig
{% set mainContent = mainContentSplit(page) %}

{{ mainContent.chapeau|raw }}      {# Content before first <!--break--> #}
{{ mainContent.intro|raw }}        {# Content before first heading (if TOC enabled) #}
{{ mainContent.content|raw }}      {# Main content #}
{{ mainContent.body|raw }}         {# Full body (intro + content) #}
{{ mainContent.toc|raw }}          {# Generated table of contents #}

{% for part in mainContent.contentParts %}
    {{ part|raw }}                 {# Content parts split by <!--break--> #}
{% endfor %}
```

The function caches results by page ID, so multiple calls in the same template have no performance penalty.

For simple templates that just need the full content without splitting:

```twig
{{ pw(page).mainContent|raw }}
```

## Creating a Custom Filter

Filters are stateless Symfony services that implement `FilterInterface` and use the `#[AsFilter]` attribute for auto-discovery.

### Step 1: Create the Filter Class

```php
<?php

namespace App\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Filter\FilterInterface;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;

#[AsFilter]
class MyCustomFilter implements FilterInterface
{
    // Inject any dependencies via constructor
    public function __construct(
        private readonly SomeService $someService,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        // Validate input
        assert(is_scalar($propertyValue));
        $value = (string) $propertyValue;

        // Apply your transformation
        return $this->someService->transform($value);
    }
}
```

### Step 2: Configure the Filter

Add your filter to the property configuration:

```yaml
pushword:
    filters:
        myProperty: [myCustomFilter]
```

The filter name is the class name in camelCase (e.g., `MyCustomFilter` becomes `myCustomFilter`).

## FilterInterface

The `apply` method receives:

| Parameter | Type | Description |
|-----------|------|-------------|
| `$propertyValue` | `mixed` | The current property value to transform |
| `$page` | `Page` | The page entity being filtered |
| `$manager` | `Manager` | The filter manager (for chaining filters) |
| `$property` | `string` | The property name being filtered |

## Events

You can hook into the filtering process using Symfony events:

- `entity_filter.before_filtering` - Dispatched before filters are applied
- `entity_filter.after_filtering` - Dispatched after filters are applied

Both events contain the `Manager` instance and the property name being filtered.

## Architecture

The Entity Filter system uses:

- **`#[AsFilter]` Attribute**: Auto-registers filters with the `pushword.entity_filter` tag
- **`FilterRegistry`**: Centralized service locator for all registered filters
- **`Manager`**: Applies filters to a specific Page entity with property caching
- **`ManagerPool`**: Factory for creating Manager instances per Page

Filters are stateless services with constructor dependency injection, making them easy to test and extend.