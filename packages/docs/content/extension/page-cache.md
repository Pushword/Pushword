---
title: 'Page Cache (static serve via Caddy / FrankenPHP)'
h1: 'Page Cache'
publishedAt: '2026-04-23 10:00'
toc: true
---

Pre-render pages into `public/cache/{host}/` so the web server (Caddy, Apache, FrankenPHP) serves them as plain files without booting PHP. Different from `pw:static` export: the same running application is still behind the cache for admin routes, dynamic fragments, and pages flagged `cache: false`.

## Install

Ships with `pushword/static-generator` — no extra package.

## Configure

Enable per-app in `config/packages/pushword.yaml`:

```yaml
pushword:
  apps:
    - hosts: [example.com]
      cache: static   # values: "none" (default) | "static"
```

Enabling `cache: static` changes three things for that app:

1. The static-generator writes to `public/cache/{host}/` instead of `static/{host}/`.
2. A `cache` checkbox appears on every page's "State" fieldset in the admin (default: on). Disable it for pages with server-side dynamic content (forms posting to PHP, live counters, etc.).
3. Saving a page fires a `PageCacheRefreshMessage` via Messenger that regenerates that single page's cached file. Deleting a page removes it.

## Warm / clear the cache

```shell
php bin/console pw:cache:clear           # clear + warm every cache:static site
php bin/console pw:cache:clear example.com
php bin/console pw:cache:clear --no-warmup
```

Run `--no-warmup` after a bulk flat import if you want to delete the cache without blocking on a full re-render.

## Dynamic fragments for logged-in users

The cached HTML is the **same for everyone** — anonymous visitors, logged-in admins, and bots. Anything that must differ per-user (admin buttons, flash messages, user menu) is loaded client-side via `liveBlock` (see `@pushword/js-helper`). The fetch is gated by the `pw_auth=1` cookie, which core sets on login success and clears on logout:

```twig
<div
  data-live="{{ path('pushword_admin_fragment_page_buttons', {id: page.id}) }}"
  data-live-if="cookie:pw_auth=1"></div>
```

- No cookie → `liveBlock` skips → zero PHP, zero network request.
- Cookie present → fetch → fragment injected → `DOMChanged` fires so icons/tooltips re-init.

The fragment endpoint stays behind the Symfony firewall; the cookie is only a client-side hint. Pattern is reusable for any dynamic block.

### htmx alternative

If your theme already loads htmx, replace `data-live` / `data-live-if` with the native htmx conditional trigger:

```html
<div
  hx-post="{{ path('pushword_admin_fragment_page_buttons', {id: page.id}) }}"
  hx-trigger="load[document.cookie.includes('pw_auth=1')]"
  hx-swap="outerHTML"></div>
```

- `hx-trigger="load[…]"` — htmx evaluates the JS expression on page load; the request is skipped when the cookie is absent, so there is no network request for anonymous visitors. `pw_auth=1` is the same cookie core sets on login and clears on logout (see above).
- `hx-swap="outerHTML"` — replaces the placeholder `<div>` with the fragment, matching the `liveBlock` behaviour.
- After the swap, subscribe to `htmx:afterSwap` (instead of `DOMChanged`) if you need to re-init icons or tooltips.

The fragment endpoint (`pushword_admin_fragment_page_buttons`) is protected by `ROLE_EDITOR`; anonymous requests receive a redirect or 403, not fragment HTML.

## Messenger

`PageCacheRefreshMessage` uses whatever transport you routed it to. With no routing, Symfony runs it synchronously (fine for dev, not great for admin UX under load). Route to an async transport in production:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    routing:
      'Pushword\StaticGenerator\Cache\Message\PageCacheRefreshMessage': async
```

Run a worker: `php bin/console messenger:consume async`.

## Flat import

`pushword/flat`'s `import` wraps its loop with the `PageCacheSuppressor`, so bulk imports don't fire thousands of Messenger messages. Run `pw:cache:clear` after a large import.

## Caddy config

Example Caddyfile snippet. The `@cached` matcher excludes admin/profiler routes so Caddy doesn't even stat cache files for them:

```caddy
{$SERVER_NAME:localhost} {
    root * /srv/app/public
    encode gzip

    @cached {
        method GET
        not path /admin* /_profiler* /_wdt*
        path_regexp cachedPath ^(/.*?)/?$
        file {
            try_files /cache/{host}{re.cachedPath.1}.html /cache/{host}/index.html
        }
    }
    handle @cached {
        file_server {
            precompressed br gzip
        }
    }

    php_server
}
```

PHP never boots for anonymous GETs on cacheable paths. Pages that don't have a cache file (dynamic ones, or freshly created pages whose Messenger job hasn't run) fall through to `php_server`.

## Verifying it works

```shell
php bin/console pw:cache:clear example.com
ls public/cache/example.com/     # expect index.html, foo.html, index.html.gz, …
curl -sI https://example.com/    # Caddy serves from disk; no PHP boot
```

Edit a page in the admin → a new Messenger message is dispatched → the cached file is updated. Delete a page → the cached file is removed.
