---
title: 'Reusable Snippets & Components for Pushword CMS'
h1: Snippet
publishedAt: '2026-05-24 10:00'
toc: true
---

Reusable fragments included in many pages:

- **Content snippets** — owned by the editor (a CTA text, an author box, a footer
  note): Markdown, one row per host, edited in the admin or as flat files.
- **Component snippets** — owned by the developer (a styled call-to-action, a
  value-props grid): a PHP class with a parameter schema and a Twig template; the
  block editor builds the form from the schema.

Both are called the same way from page content:

```twig
{{ snippet('footer-note') }}
{{ snippet('cta', { title: 'Ready to start?', buttonText: 'Contact us', buttonUrl: '/contact' }) }}
```

## Install

```shell
composer require pushword/snippet
```

Create the table:

```shell
php bin/console doctrine:schema:update --force
```

## The `snippet()` Twig function

```twig
{{ snippet(name, params = {}) }}
```

Resolution order:

1. A dev-registered **component snippet** named `name` (see below).
2. Otherwise, a **content snippet** whose `slug` is `name`, for the current host.
3. Otherwise, a **global** content snippet of that slug (the "All hosts" fallback).
4. Otherwise, an empty string.

`params` is an optional map. For component snippets it is passed to the template
(and validated against the schema in the editor). For content snippets it is
exposed to the snippet's own Twig as `params` and as top-level variables.

## Content snippets

A `Snippet` entity: a `slug` (the reference key, unique per host), a `name` (admin
label), Markdown `content`, optional `tags` and custom properties. It renders through
the **same filter pipeline as a page** (Twig → Markdown → multisite links →
ShowMore…), and its admin form uses the page editor (block editor when
`pushword/admin-block-editor` is installed).

### Host and "All hosts"

The host picker lists your hosts plus **All hosts** (stored as an empty host): a
**global fallback** used on every host, overridden by a host-specific snippet of the
same slug. An empty slug is filled from the name on creation.

Manage snippets from the admin (**Snippets** in the menu) or as flat files (see
[Flat-file sync](#flat-file-sync)):

```markdown
<!-- {flat_content_dir}/pw-snippets/footer-note.md -->
---
name: Footer note
tags:
  - global
---
Need a hand? [Contact us](/contact) — we usually reply within a day.
```

The **filename is the slug** and the host comes from the content directory; any
extra frontmatter key becomes a custom property. Content snippets accept params too:

```markdown
# {{ params.heading|default('Welcome') }}
```

```twig
{{ snippet('greeting', { heading: 'Hello there' }) }}
```

## Component snippets

Declare a class with `#[AsSnippet]`, a parameter schema, and a Twig template:

```php
namespace App\Snippet;

use Pushword\Snippet\Attribute\AsSnippet;
use Pushword\Snippet\Component\AbstractSnippetComponent;

#[AsSnippet(name: 'cta', template: 'snippet/cta.html.twig', label: 'Call to action')]
final class CtaSnippet extends AbstractSnippetComponent
{
    public function getSchema(): array
    {
        return [
            'title' => ['type' => 'string', 'label' => 'Title'],
            'description' => ['type' => 'text', 'label' => 'Description'],
            'buttonText' => ['type' => 'string', 'label' => 'Button label'],
            'buttonUrl' => ['type' => 'string', 'label' => 'Button URL'],
        ];
    }

    // optional: apply defaults / cast types before rendering
    public function prepareParams(array $params): array
    {
        return $params + ['buttonUrl' => '#'];
    }
}
```

```twig
{# templates/snippet/cta.html.twig #}
<div class="cta">
  {% if params.title %}<h2>{{ params.title }}</h2>{% endif %}
  {% if params.description %}<p>{{ params.description }}</p>{% endif %}
  {% if params.buttonText %}<a href="{{ params.buttonUrl }}">{{ params.buttonText }}</a>{% endif %}
</div>
```

The template receives `params` (the prepared map), each param as a top-level
variable, and the current `page`.

### Schema field types

Supported `type`s:

| Type         | Editor control            | Stored value          |
| ------------ | ------------------------- | --------------------- |
| `string`     | single-line input         | string                |
| `text`       | textarea                  | string                |
| `bool`       | checkbox                  | boolean               |
| `select`     | dropdown (`options` list) | string                |
| `media`      | media picker              | filename string       |
| `collection` | repeatable rows (`fields`)| list of objects       |

`collection` example (the recurring `{icon, title, text}` card pattern):

```php
'items' => ['type' => 'collection', 'label' => 'Cards', 'fields' => [
    'icon' => ['type' => 'string'],
    'title' => ['type' => 'string'],
    'text' => ['type' => 'text'],
]],
```

## Block editor

With `pushword/admin-block-editor`, a **Snippet** block lists every snippet available
for the page's host and builds a form from the selected component's schema (content
snippets get a free-form JSON params field). It round-trips to a call with JSON params:

```twig
{{ snippet('cta', {"title":"Ready to start?","buttonText":"Contact us","buttonUrl":"/contact"}) }}
```

Only a **standalone** call becomes a block; an inline `{{ snippet(...) }}` inside a
paragraph stays in the prose. Hand-written params may use Twig (`{ title: '…' }`) or
JSON (`{"title":"…"}`) syntax; both round-trip through flat files and the editor.

## Flat-file sync

With `pushword/flat` installed, content snippets sync to and from
`{flat_content_dir}/pw-snippets/{slug}.md` (one Markdown file per snippet, YAML
frontmatter for `name`, `tags` and custom properties). The page importer skips
`pw-` directories:

```bash
php bin/console pw:flat:sync --entity=snippet            # auto-detect direction
php bin/console pw:flat:sync --entity=snippet -m export  # DB → files
php bin/console pw:flat:sync --entity=snippet -m import  # files → DB
```

Host-scoped snippets live in `{content}/{host}/pw-snippets/`; global ones in the base
`{content}/pw-snippets/`, synced during the default app's primary host pass — so
`pw:flat:sync some-other-host` leaves them untouched. `--entity=all` (the default)
includes snippets; direction is detected as for pages. Component snippets are code:
versioned by git, not synced.

## Versioning

With [`pushword/version`](/extension/version), content snippets get the page history:
a **Versions** action in the Snippet admin, snapshots under
`var/log/version/snippet/{id}/`.

## Migration guide — from custom Twig functions to component snippets

A bespoke Twig function (`ctaBlock()`, `valueProps()`…) rendering a template becomes a
component snippet reusing that template, and gains an editor form. Take:

```php
// Before — src/Twig/AppExtension.php
#[AsTwigFunction('ctaBlock', isSafe: ['html'])]
public function ctaBlock(string $title, string $description = '', string $buttonText = '', string $action = '#'): string
{
    return $this->twig->render('component/cta.html.twig', [
        'title' => $title, 'description' => $description,
        'buttonText' => $buttonText, 'action' => $action,
    ]);
}
```

### 1. Declare the component

Point it at the **same template**, listing the parameters as a schema:

```php
// After — src/Snippet/CtaSnippet.php
#[AsSnippet(name: 'cta', template: 'component/cta.html.twig')]
final class CtaSnippet extends AbstractSnippetComponent
{
    public function getSchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'description' => ['type' => 'text'],
            'buttonText' => ['type' => 'string'],
            'action' => ['type' => 'string'],
        ];
    }

    public function prepareParams(array $params): array
    {
        return $params + ['action' => '#'];
    }
}
```

### 2. Adjust the template to read from `params`

The template now receives a `params` map instead of separate variables:

```twig
{# component/cta.html.twig #}
-<h2>{{ title }}</h2>
+<h2>{{ params.title }}</h2>
```

Top-level variables still work (each param is also exposed by name), so this step is
optional.

### 3. Update content calls

```twig
-{{ ctaBlock('Ready?', 'Join us today', 'Sign up', '/register') }}
+{{ snippet('cta', { title: 'Ready?', description: 'Join us today', buttonText: 'Sign up', action: '/register' }) }}
```

To migrate gradually, keep the old function as an alias meanwhile:

```php
#[AsTwigFunction('ctaBlock', isSafe: ['html'])]
public function ctaBlock(string $title, string $description = '', string $buttonText = '', string $action = '#'): string
{
    return $this->snippetExtension->renderSnippet('cta', compact('title', 'description', 'buttonText', 'action'));
}
```

Array-of-objects arguments (`valueProps([{icon,title,text}, …])`) map onto a
`collection` field.
