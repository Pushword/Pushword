---
title: 'How to override default theme with Pushword CMS ? '
h1: 'Customize the current theme'
publishedAt: '2025-12-21 21:55'
toc: true
---

Override a template per site ([app way](#app)) or for every site ([bundle way](#symfony)).

## The Pushword App Way {id=app}

Create a file in `./templates/{$host}/page/` named like the #[default one](https://github.com/Pushword/Pushword/tree/main/packages/core/src/templates/page) — e.g. `./templates/{$host}/page/_navbar.html.twig` replaces the navbar.

Example: #[this documentation's templates](https://github.com/Pushword/Pushword/tree/main/packages/dev-app/templates/pushword.piedweb.com/page).

## The Bundle Way {id=symfony}

Every package is a Symfony bundle, so #[Symfony's bundle override](https://symfony.com/doc/current/bundles/override.html) applies: copy the template under `templates/bundles/`. For `page/page_default.html.twig`:

```
./templates/bundles/PushwordCoreBundle/page/page_default.html.twig
```

## Custom Error Page (404) {#error-page}

A page with the slug `404` is rendered for missing URLs. Without one, a generic template shows the translated `errorTitle` and `errorDescription` messages.

[Static sites](/extension/static-generator) get one `404.html` per locale. The generated server configs route by URL prefix: `/fr/…` serves `/fr/404.html` (a `fr/.htaccess` on Apache, a `handle_errors` matcher on Caddy), everything else the root `404.html`. A `fr/404` page, when it exists, is the one written there.

A `404` or `<locale>/404` page is always `noindex` without canonical, whatever its robots field, and is left out of the sitemap, feeds, search index and `pages_list()`. At its own URL it answers HTTP 404 — in the app, the generated `.htaccess`/`.Caddyfile`, and [cache mode](/extension/page-cache), which never caches it.
