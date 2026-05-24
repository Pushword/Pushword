---
title: 'Reusable Snippets & Components for Pushword CMS'
h1: Snippet
publishedAt: '2026-05-24 10:00'
toc: true
---

Editor-owned reusable content fragments and dev-registered components, included in
many pages, edited from the admin _and_ as flat files, translated per host —
without the developer touching a Twig template for each one.

It unifies two things that used to be separate:

- **Content snippets** — reusable _content_ owned by the editor (a CTA text, an
  author box, a footer note). Stored as Markdown, one row per host/locale.
- **Component snippets** — reusable _components_ owned by the developer (a styled
  call-to-action, a value-props grid). Declared once in PHP with a parameter
  schema and a Twig template; the block editor builds the form for free.

Both are invoked the same way, from page content:

```twig
{{ snippet('footer-note') }}
{{ snippet('cta', { title: 'Ready to start?', buttonText: 'Contact us', buttonUrl: '/contact' }) }}
```

## Install

```shell
composer require pushword/snippet
```

Register the bundle (done automatically by Symfony Flex):

```php
// config/bundles.php
Pushword\Snippet\PushwordSnippetBundle::class => ['all' => true],
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
3. Otherwise, an empty string.

`params` is an optional map. For component snippets it is passed to the template
(and validated against the schema in the editor). For content snippets it is
exposed to the snippet's own Twig as `params` and as top-level variables.

## Content snippets

A content snippet is a `Snippet` entity: a `slug` (the reference key, unique per
host), a `name` (admin label), Markdown `content`, optional `tags`, and a custom
property bag. It is rendered through the **same filter pipeline as a page**
(Twig → Markdown → multisite links → ShowMore…), so everything you can write in a
page works in a snippet.

Manage them from the admin (**Snippets** in the menu) or as flat files via
`pushword/flat` (see [Flat-file sync](#flat-file-sync)).

```markdown
<!-- {flat_content_dir}/snippets/footer-note.md -->
---
name: Footer note
tags:
  - global
---
Need a hand? [Contact us](/contact) — we usually reply within a day.
```

The **filename is the slug** and the host comes from the content directory, so
neither is repeated in the frontmatter; any extra key becomes a custom property.

Use it:

```twig
{{ snippet('footer-note') }}
```

Content snippets accept params too:

```markdown
# {{ params.heading|default('Welcome') }}
```

```twig
{{ snippet('greeting', { heading: 'Hello there' }) }}
```

## Component snippets

A component snippet replaces the bespoke custom Twig functions sites used to
write by hand. Declare a class with `#[AsSnippet]`, a parameter schema, and a
Twig template:

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

The schema drives both rendering and the block-editor form. Supported `type`s:

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

When `pushword/admin-block-editor` is installed, a **Snippet** block is added to
the editor toolbar automatically. It lists every snippet available for the page's
host (components _and_ content snippets), and builds a form from the selected
component's schema. Content snippets (no schema) expose a free-form JSON params
field.

The block round-trips to a `snippet('name', {params})` call in Markdown, the
params serialised as a JSON object argument:

```twig
{{ snippet('cta', {"title":"Ready to start?","buttonText":"Contact us","buttonUrl":"/contact"}) }}
```

Only a **standalone** snippet call becomes a block; inline `{{ snippet(...) }}`
mentions inside a paragraph stay in the prose. The integration is contributed
through `EditorJsToolProviderInterface`, so the block editor stays unaware of
snippets and the block disappears cleanly when `pushword/snippet` is absent.

## Writing snippet calls by hand

You can always insert snippet calls directly in Markdown — exactly like the
custom Twig functions they replace:

```twig
{{ snippet('cta', { title: 'Ready to start?', buttonText: 'Contact us', buttonUrl: '/contact' }) }}
```

The params map accepts inline Twig (`{ title: '…' }`) or JSON
(`{"title":"…"}`) — both round-trip cleanly through flat files and the editor
(single-quoted/JSON5-ish objects are repaired on import).

## Flat-file sync

With `pushword/flat` installed, content snippets sync to and from
`{flat_content_dir}/snippets/{slug}.md` (one Markdown file per snippet, YAML
frontmatter for `name`, `tags` and custom properties):

```bash
php bin/console pw:flat:sync --entity=snippet            # auto-detect direction
php bin/console pw:flat:sync --entity=snippet -m export  # DB → files
php bin/console pw:flat:sync --entity=snippet -m import  # files → DB
```

`--entity=all` (the default) includes snippets alongside pages and media.
Direction is detected from file modification times against the last sync, the
same way pages work. Component snippets are code, so they are versioned by git,
not synced.

## Roadmap

- **Version** (`pushword/version`): extend the versioning listener to cover the
  `Snippet` entity so content snippets get the same version/restore history as
  pages. The versioner is currently `Page`-typed, so this is a real refactor.
  Component snippets are versioned by git (they are code).

## Migration guide — from custom Twig functions to component snippets

Sites commonly ship bespoke Twig functions (`ctaBlock()`, `valueProps()`,
`reservation_box()`, `gpx()`…): a PHP `AsTwigFunction` plus a template, with the
editor hand-typing the call into Markdown. Component snippets give the same
output **plus** an editor form, validation, and discoverability — usually
**reusing the template you already have**.

Take an existing function:

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

(Top-level variables still work — each param is also exposed by name — so a
template using `{{ title }}` keeps rendering. Prefer `params.title` going forward.)

### 3. Update content calls

```twig
-{{ ctaBlock('Ready?', 'Join us today', 'Sign up', '/register') }}
+{{ snippet('cta', { title: 'Ready?', description: 'Join us today', buttonText: 'Sign up', action: '/register' }) }}
```

For a gradual migration, keep the old function as a one-line alias while you
update content:

```php
#[AsTwigFunction('ctaBlock', isSafe: ['html'])]
public function ctaBlock(string $title, string $description = '', string $buttonText = '', string $action = '#'): string
{
    return $this->snippetExtension->renderSnippet('cta', compact('title', 'description', 'buttonText', 'action'));
}
```

The collection-style functions (`valueProps([{icon,title,text}, …])`) map directly
onto a `collection` schema field, so the array-of-objects you already pass in
content keeps working.
