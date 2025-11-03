---
title: 'Insert SVG/Icon in your content - Pushword CMS'
h1: SVG
toc: true
parent: extensions
---

SVG Twig Extension is in the core (previously, it was a dedicated extension `puswhord/svg`).

## Usage

By default, it's use #[FontAwesome](https://fontawesome.com/icons). You just need to use twig `svg` function :

```twig
{{ svg('surprise') }}
```

Will show <span style="width:16px;height:16px; display:inline-block;color:var(--primary)" class="fill-current">{{ svg('surprise') }}</span>

## Configure

By default, this extension load #[FontAwesome](https://fontawesome.com/icons)'s icons.

You can choose to use your custom SVG icons by specifyng the `svg_dir` in configuration (default value is `['%kernel.project_dir%/templates/icons', '...fontawesomeVendorDir...', '%kernel.project_dir%/public/bundles/pushwordcore']`).
