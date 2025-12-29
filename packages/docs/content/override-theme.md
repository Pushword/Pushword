---
title: 'How to override default theme with Pushword CMS ? '
h1: 'Customize the current theme'
id: 34
publishedAt: '2025-12-21 21:55'
parentPage: themes
toc: true
---

There is at least two ways to override template file and customize the html wich is rendered by Pushword : First is [App Way](#app) (app per app), second is the [bundle way](#symfony) (global).

## The Pushword App Way {id=app}

Simplest way is to override it (partially or completly) by create a new file in `./templates/{$host}/page` naming it like the #[default one](https://github.com/Pushword/Pushword/tree/main/packages/core/src/templates/page)

Eg: Overriding the default navbar can be done creating a file `./templates/{$host}/page/_navbar.html.twig`.

You can see how it's handle #[for the documentation](https://github.com/Pushword/Pushword/tree/main/packages/skeleton/templates/pushword.piedweb.com/page).

## The Bundle Way {id=symfony}

Every package (even core) is built like a symfony bundle, so you can #[override template file the bundle way](https://symfony.com/doc/current/bundles/override.html).

For this, you just need to find the template file you want to override and create a copy inside your `templates/bundles` folder like.

Example, to override `/page/page_default.html.twig` :

```
./templates/bundles/PushwordCoreBundle/page/page_default.html.twig
```

---

- #[See default core template file](https://github.com/Pushword/Pushword/tree/main/packages/core/src/templates/page)