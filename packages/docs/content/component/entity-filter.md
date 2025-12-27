---
title: 'Entity Filter - Transform Page properties with filters'
h1: 'Entity Filter'
id: 23
publishedAt: '2025-12-21 21:55'
parentPage: homepage
name: 'Entity Filter'
filter_twig: 0
---

The **Entity Filter** is one of the most powerful components in Pushword. It allows you to apply transformations on entity properties (mostly `Page`) through a flexible and extensible filter system.

## Usage

Filters can be used from **PHP** via `Pushword\Core\Component\EntityFilter\Manager` or directly in **Twig** templates with the `pw` function:

```twig
{# Render markdown in title #}
{{ pw(page).title|raw }}

{# Get processed main content #}
{{ pw(page).mainContent }}
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
        mainContent: [twig, markdown, mainContentSplitter]
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
| `mainContentSplitter` | Splits content into chapeau, intro, and parts |
| `mainContentToBody` | Wraps content in a ContentBody value object |
| `markdown` | Converts markdown to HTML |
| `name` | Extracts first line as name |
| `showMore` | Handles show-more content sections |
| `twig` | Renders Twig expressions in content |

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

## Value Objects

Some filters return value objects instead of strings for complex data:

### SplitContent

Returned by `mainContentSplitter`, provides structured access to content parts:

```twig
{% set content = pw(page).mainContent %}

{{ content.chapeau }}     {# Content before first <!--break--> #}
{{ content.intro }}       {# Content before first heading (if TOC enabled) #}
{{ content.content }}     {# Main content #}
{{ content.body }}        {# Full body (intro + content) #}
{{ content.contentParts }} {# Array of content parts split by <!--break--> #}
{{ content.toc }}         {# Generated table of contents #}
```

### ContentBody

Returned by `mainContentToBody`, wraps content with a `getBody()` method:

```twig
{% set body = pw(page).body %}
{{ body.body }}
```

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