---
title: 'Page HERO and custom field in admin to manage main image format'
h1: 'Advanced Main Image'
publishedAt: '2025-12-21 21:55'
toc: true
---

Adds a *main image format* field to the page form of the [admin](/extension/admin) —
from hidden to full-screen hero — and the templates that render it.

## Install

```shell
composer require pushword/advanced-main-image
```

## Configuration

Globally, or per site in the app configuration:

```yaml
pushword_advanced_main_image:
  advanced_main_image: true # false disables the field
  main_image_formats: # translation key => stored value (defaults shown)
    adminPageMainImageFormatNormal: 0
    adminPageMainImageFormatNone: 1
    adminPageMainImageFormat13fullscreen: 2
    adminPageMainImageFormat34fullscreen: 3
```

The value is stored in the page's `mainImageFormat` custom property.

## Templates

The bundle overrides `page/page.html.twig`; a site overriding it too must keep the switch.

- `0` (normal) and `1` (none) render through the default `page/_content.html.twig`.
- Above `1`, `page/page_hero.html.twig` and `page/_content_hero.html.twig` take over.
