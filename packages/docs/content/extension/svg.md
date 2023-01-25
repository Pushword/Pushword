---
title: 'Insert SVG/Icon in your content - Pushword CMS'
h1: SVG
toc: true
parent: extensions
---

Add SVG with ease in your main content or in a template file via a twig function.

## Install

```shell
composer require pushword/svg
```

## Usage

By default, this extension use #[FontAwesome 5.15](https://fontawesome.com/icons). You just need to use twig `svg` function :

```twig
svg('surprise')
```

Will show <span style="width:16px;height:16px; display:inline-block;color:var(--primary)" class="fill-current">{{ svg('surprise') }}</span>

## Configure

By default, this extension load #[FontAwesome 5.15](https://fontawesome.com/icons)'s icons. You can choose to use your custom SVG icons by specifyng the `svg_dir` in your app config or under `pushword_svg`.
