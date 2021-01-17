---
title: "How to override default theme with Pushword CMS ? "
h1: Customize/Override the current theme
toc: true
parent: themes
---

There is multiple way to #[override the default theme](https://symfony.com/doc/current/bundles/override.html).

Simplest way is to override it (partially or completly) by create a new file in `./templates/{$host}/page` naming it like the #[default one](https://github.com/Pushword/Pushword/tree/main/packages/core/src/templates/page)

Eg: Overriding the default navbar can be done creating a file `./templates/{$host}/page/_navbar.html.twig`.

You can see how it's handle #[for the documentation](https://github.com/Pushword/Pushword/tree/main/packages/skeleton/templates/pushword.piedweb.com/page).
