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

## Staleness beyond page saves: the render epoch

A cached page's HTML depends on more than its own row: snippets, media, edited
templates, reviews, and other pages (listings, navs, breadcrumbs, feeds). Each
host carries a **render epoch** — an opaque token, stored in a small filesystem
pool under `var/cache/{env}/pw_render_epoch/` — that any such change bumps.
Every generated page is stamped with the epoch it was rendered under; a
mismatch means "stale", whatever the cause. The storage is deliberately not
`cache.app`: web and CLI must see the same token, and `cache.app` is often APCu
(per-process, absent on CLI).

What bumps the epoch:

| Change | Scope |
|---|---|
| Snippet create/edit/delete | its host (all hosts when host-less) |
| Media edit/delete (not upload — new files can't be referenced yet) | all hosts |
| Template save/delete in the template editor | all hosts |
| Review/message publication, edit or removal (conversation) | its host |
| Page create/delete, or an edit that can affect *other* pages | its host |

For that last row: metadata renders elsewhere (title, h1, name, slug, parent,
publication date, weight, locale, main image, host) always counts, and a content
edit counts only when it adds or removes an internal link (the link graph feeds
`linked_slugs`, `exclude_linked`, `contains_link_to`). A plain prose edit stays
on the instant single-page path and never triggers a sweep.

A bump queues one `HostCacheRefreshMessage` per affected cache-mode host,
dispatched at `kernel.terminate` (after the response) with a 60s `DelayStamp` so
editing bursts coalesce. The handler runs an **incremental sweep** of the host:
pages whose stored epoch matches are skipped; re-rendered pages whose HTML is
byte-identical skip the write, so a sweep costs CPU but no disk churn and no
cache-header churn for unaffected pages. A completed sweep records the epoch it
sampled at start, and later messages for that epoch no-op — an editor saving
every 30 seconds triggers one sweep, not twenty.

If a sweep is lost (process killed, no worker running), nothing goes
permanently stale: the epoch comparison is durable in
`var/.static-generation-state.json`, and the next `pw:static --incremental`
run (cron it on big hosts) or `pw:cache:clear` converges.

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

### Gates and deferred triggers

`data-live-if` accepts two gate prefixes, both string-matched (no `eval`, so
they work under a strict CSP): `cookie:NAME=VALUE` (above) and
`media:(min-width: 640px)` — the block loads only when the media query
matches, and a widened window or rotated phone is picked up automatically.
Unknown prefixes fail closed: the block is skipped, never fetched by accident.

`data-live-trigger="my-event"` defers the fetch until `my-event` fires on
`window` (e.g. a modal opening), once by default; add `data-live-repeat` to
refetch on every occurrence into the surviving container.

### With htmx 4 on the page

If your theme loads htmx (>= 4), `liveBlock` stops fetching and **aliases**
every `data-live` block to native htmx attributes automatically — templates
need no change, htmx becomes the single request engine, and js-helper installs
a two-way bridge (`htmx:after:swap` → `DOMChanged`, and `DOMChanged` →
`htmx.process()` so content added by Alpine or other scripts is discovered).
Gates keep their eval-free semantics, and a 4xx/5xx response never replaces an
aliased block.

You can also author htmx syntax directly:

```html
<div
  hx-post="{{ path('pushword_admin_fragment_page_buttons', {id: page.id}) }}"
  hx-trigger="load[document.cookie.includes('pw_auth=1')]"
  hx-swap="outerHTML"></div>
```

- `hx-trigger="load[…]"` evaluates the JS expression at process time — this
  variant needs `unsafe-eval` under a strict CSP; the `data-live-if` gates do
  not.
- htmx 4 swaps 4xx/5xx response bodies by default. Restore the no-swap
  behaviour globally with
  `<meta name="htmx-config" content='noSwap:[204, 304, "4xx", "5xx"]'>` or
  per element with `hx-status:4xx="swap:none"`.
- Cross-origin fragment hosts need `hx-config='credentials:"include"'`
  (`data-live` always sends credentials).
- htmx 4 is in beta (the admin bundle runs a pinned version); for public
  downstream sites prefer `data-live` until the stable release.

The fragment endpoint (`pushword_admin_fragment_page_buttons`) is protected by `ROLE_EDITOR`; anonymous requests receive a redirect or 403, not fragment HTML.

## Messenger

Both messages use whatever transport you route them to. With no routing, Symfony
runs them synchronously — fine for `PageCacheRefreshMessage` (one page, ~10ms),
and survivable for `HostCacheRefreshMessage` because it is dispatched after the
response is sent; but the PHP worker then spends the whole sweep duration on it,
and the 60s coalescing delay is ignored. Route both to an async transport in
production:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    routing:
      'Pushword\StaticGenerator\Cache\Message\PageCacheRefreshMessage': async
      'Pushword\StaticGenerator\Cache\Message\HostCacheRefreshMessage': async
```

Run a worker: `php bin/console messenger:consume async`.

Rule of thumb per install:

- **Small host, no worker**: keep everything unrouted. Sweeps run inline after
  the response; at a few dozen pages that is well under a second.
- **Hundreds of pages or more**: route `HostCacheRefreshMessage` async (the
  delay then debounces editing bursts), or skip the message path entirely and
  cron `pw:static --incremental` — the epoch comparison is durable, so the cron
  picks up everything the messages would have.
- **Full-static sites (no `cache: static`)**: these messages never fire; the
  epoch still works for you through `pw:static --incremental`.

## Flat import

`pushword/flat`'s `import` wraps its loop with the `PageCacheSuppressor`, so bulk imports don't fire thousands of Messenger messages and a `--force` re-import doesn't delete the cached files it is about to recreate. The render epoch still moves for every listing-relevant change: follow the import with `pw:static --incremental` (the `pushword-deploy publish` default) and it resweeps exactly what the import staled. `pw:cache:clear` stays the hard reset.

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
