---
title: 'How to override default theme with Pushword CMS ? '
h1: 'Customize the current theme'
publishedAt: '2025-12-21 21:55'
toc: true
---

There is at least two ways to override template file and customize the html wich is rendered by Pushword : First is [App Way](#app) (app per app), second is the [bundle way](#symfony) (global).

## The Pushword App Way {id=app}

Simplest way is to override it (partially or completly) by create a new file in `./templates/{$host}/page` naming it like the #[default one](https://github.com/Pushword/Pushword/tree/main/packages/core/src/templates/page)

Eg: Overriding the default navbar can be done creating a file `./templates/{$host}/page/_navbar.html.twig`.

You can see how it's handle #[for the documentation](https://github.com/Pushword/Pushword/tree/main/packages/dev-app/templates/pushword.piedweb.com/page).

## The Bundle Way {id=symfony}

Every package (even core) is built like a symfony bundle, so you can #[override template file the bundle way](https://symfony.com/doc/current/bundles/override.html).

For this, you just need to find the template file you want to override and create a copy inside your `templates/bundles` folder like.

Example, to override `/page/page_default.html.twig` :

```
./templates/bundles/PushwordCoreBundle/page/page_default.html.twig
```

## Custom Error Page (404) {#error-page}

Create a page with the slug `404` in the admin to customize your error page. Pushword will automatically render it when a visitor hits a missing URL.

If no `404` page exists, Pushword falls back to a generic error template displaying translated messages (`errorTitle`, `errorDescription`).

For **static sites** (using [static-generator](/extension/static-generator)), a `404.html` file is generated automatically from the same mechanism — one per configured locale. The generated server configs route errors to the right one by URL prefix: `/fr/…` serves `/fr/404.html` (via a small `fr/.htaccess` on Apache, a matcher in `handle_errors` on Caddy), everything else serves the root `404.html`. A `fr/404` page, when it exists, is the one written there.

The `404` page, and a `<locale>/404` one, stays out of the index whatever its robots field says: it renders `noindex` with no canonical, and is left out of the sitemap, the feeds, the search index and `pages_list()`. Visited at its own URL, it answers `404` too — in the app, in the generated `.htaccess` and `.Caddyfile`, and in [cache mode](/extension/page-cache), which never caches it so PHP can.

---

- #[See default core template file](https://github.com/Pushword/Pushword/tree/main/packages/core/src/templates/page)
