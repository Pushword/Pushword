---
title: 'Insert SVG/Icon in your content - Pushword CMS'
h1: SVG
publishedAt: '2025-12-21 21:55'
toc: true
---

SVG Twig Extension is in the core (previously, it was a dedicated extension `pushword/svg`).

## Usage

By default, it's use #[FontAwesome](https://fontawesome.com/icons). You just need to use twig `svg` function :

```twig
{{ svg('surprise') }}
```

Will show <span style="width:16px;height:16px; display:inline-block;color:var(--primary)" class="fill-current">{{ svg('surprise') }}</span>

## Configure

By default, this extension load #[FontAwesome](https://fontawesome.com/icons)'s icons.

You can choose to use your custom SVG icons by specifying the `svg_dir` in configuration. Default paths include:
- `%kernel.project_dir%/templates/icons`
- FontAwesome directories (solid, regular, brands - both free and standard)
- `%kernel.project_dir%/public/bundles/pushwordcore`