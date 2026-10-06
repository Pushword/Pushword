---
title: 'Static Website Generator with Pushword CMS'
h1: 'Static Generator'
publishedAt: '2025-12-21 21:55'
toc: true
---

Export a site for GitHub Pages, Apache, FrankenPHP or Caddy with one command or
from the [admin](/extension/admin).

## Install

```shell
composer require pushword/static-generator
```

Custom installations (not the [default installer](/installation)): see
`vendor/pushword/static-generator/install.php`.

## Configure

Per app in `config/packages/pushword.yaml`, or globally under `static_generator:`.

```yaml
# In pushword.yaml under your app config:
pushword:
  apps:
    - hosts: [example.tld]
      static_generators: apache
      static_symlink: true
      static_dir: '%kernel.project_dir%/static/{main_host}'
      static_assets: ['assets', 'bundles'] # files/folders from public/ to copy

# Or globally in config/packages/static_generator.yaml:
static_generator:
  static_generators: apache # shortcuts: apache, github, frankenphp
  static_symlink: true
  static_dir: '%kernel.project_dir%/static/{main_host}'
```

The default generators write both an `.htaccess` (Apache/LiteSpeed) and a `.Caddyfile`
(Caddy/FrankenPHP).

### `static_symlink`

Whether media and assets are symlinked or copied into the output.

| Value | Media | Assets |
|---|---|---|
| `true` (default) | symlink | symlink |
| `false` | copy | copy |
| `['media']` | symlink | copy |
| `['assets']` | copy | symlink |
| `['media', 'assets']` | symlink | symlink |

`['media']` is the usual array form: media symlinked (fast, no extra disk), assets
copied so they deploy independently. GitHub Pages (CNAME generator) always copies.

### `static_assets`

Files or folders of `public/` copied into the output. Default: `['assets', 'bundles']`.
`static_assets_clean: true` empties the copied folders first, dropping stale hashed
(Vite) files. `static_copy` is the deprecated name of `static_assets`.

### `static_html_max_age`

Cache TTL for HTML pages (in seconds). Default: `10800` (3 hours).

```yaml
static_html_max_age: 86400 # 24 hours
```

### `static_html_stale_while_revalidate`

`stale-while-revalidate` added to the HTML `Cache-Control`; `0` disables it. Default:
`3600` (1 hour).

```yaml
static_html_stale_while_revalidate: 0 # disabled
```

Assets (images, JS, CSS, fonts) always get a 1-year TTL.

## Command

```shell
# Generate all apps
php bin/console pw:static

# Generate 1 app
php bin/console pw:static $host

# (re)Generate only one page
php bin/console pw:static $host $slug

# Only regenerate what changed since the last run
php bin/console pw:static $host --incremental
```

A page dated in the future is not generated until that date. The command lists each one
it leaves out, with its date in the
[editorial timezone](/extension/flat#dates-and-time-zones):
`Scheduled localhost.dev/launch (not generated before 2026-09-30 16:00+02:00)`. The
agent JSON carries them under `scheduled`. A [scheduled command](/background-tasks#scheduled-commands)
on `publish` regenerates them once the date passes.

### Incremental generation

`--incremental` skips a page when neither its `updatedAt` nor the host's
**render epoch** moved since it was last generated (state lives in
`var/.static-generation-state.json`). The epoch is bumped by anything that can
change rendered HTML without touching the page row: snippet, media and template
edits, review publications, and edits of *other* pages that listings render —
see [the render epoch](/extension/page-cache#staleness-beyond-page-saves-the-render-epoch).
An incremental cron therefore converges even on changes no `updatedAt` reflects.
Byte-identical re-renders skip the write, keeping deploy diffs and rsyncs quiet.

Pages deleted or unpublished since the last run are pruned: the in-place build
removes their generated files and compression sidecars along with their state
entries (the full build gets this for free from its atomic dir swap). Only
pager files (`slug/2.html`) wait for the next full build — their directory also
holds child pages' output.

Bulk flat imports bump the epoch too — the `PageCacheSuppressor` mutes the
per-page messages, never the bump — so `pw:flat:sync && pw:static --incremental`
is a complete publish chain. It is the `pushword-deploy publish` default.

`bin/console cache:clear` wipes the epoch storage (a file pool under
`var/cache/{env}/pw_render_epoch/`): the first incremental run after a deploy
regenerates everything — which is correct, since a deploy may change templates
or code.

### From the API

The same three scopes are reachable over HTTP once [pushword/api](/extension/api) is
installed, so a script or an agent editing pages remotely can publish its own changes:
`POST /api/static/{host}/{slug}` rebuilds one page synchronously, `POST /api/static/{host}`
starts a background pass and returns a URL to poll. See
[Static regeneration](/extension/api#static).

### Performance

Hosts with 10+ pages are rendered by parallel workers, as many as CPUs and free memory
(~100 MB each) allow; `--workers=N` forces a count, `--workers=1` a sequential build.

Each worker must find every published slug assigned to it: a missing page fails the
build rather than publishing a partial export. A full build also refuses to replace a
site that has an `index.html` with an export that has none — delete the old
`index.html` from `static_dir` first if removing the homepage is intentional (including
a homepage turned into a redirect, `/` → `/en/`). A host with no published page left
but an `index.html` on disk is skipped, named in the error, and `pw:static` exits
non-zero; other hosts are still built.

Workers run without an opcache file cache (it segfaults some PHP builds). The parent
process can opt in if your PHP is not affected — keep the validation flag, since the
cache outlives `composer update`:

```shell
php -d opcache.enable_cli=1 -d opcache.file_cache=var/cache/opcache -d opcache.validate_timestamps=1 bin/console pw:static
```

## Page cache mode (serve pre-rendered pages without exporting)

`cache: static` on an app pre-renders pages into `public/cache/{host}/`, served by the
web server without PHP while the application keeps running and refreshes the cache on
save. See [Page Cache](/extension/page-cache).

## Using FrankenPHP (Caddy) to serve your static website

Import the generated `static/example.tld/.Caddyfile` in your main Caddyfile (the
[dev-app Caddyfile](https://github.com/pushword/pushword/blob/main/packages/dev-app/Caddyfile)
has it commented out at the end):

```Caddyfile
import static/example.tld/.Caddyfile
```
