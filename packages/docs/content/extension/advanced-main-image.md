---
title: "Page HERO and custom field in admin to manage main image format"
h1: Advanced Main Image
toc: true
parent: extensions
---

Supercharge the Pushword Admin with a new admin form field to customize the main image format from not visible to HERO + default template files.

## Install

```shell
composer require pushword/advanced-main-imaged
```

## Configuration

If you want to go forward than a default install (activating advanced image field for every ), you can override default parameters in your config :

```yaml
---
advanced_main_image: true # Set false to disable block editor for new page (because new page does not have an associated `app` yet)
```

If you override the default `page/page.html.twig`, the extension may not work properly.

## Customization

When mainImageFormat is set to **default** (0) or **none** (1), it's ever managed by the default `page/_content.html.twig`.

When mainImageFormat is greater than 1, it's managed by new template files added by this extension :

-   `page/page_hero.html.twig`.
-   `page/_content_hero_.html.twig`.
