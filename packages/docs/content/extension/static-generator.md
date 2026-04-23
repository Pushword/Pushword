---
title: 'Static Website Generator with Pushword CMS'
h1: 'Static Generator'
publishedAt: '2025-12-21 21:55'
toc: true
---

Generate a static website serve by github pages, apaches with one command or via the [admin](https://pushword.piedweb.com/extension/admin).

## Install

```shell
composer require pushword/static-generator
```

That's it ! If you have a custom installation (not used the [default installer](/installation)),
you may have a look inside `vendor/pushword/admin/install.php`.

## Configure

Add in your current `config/packages/pushword.yaml` for an App or globally under `static_generator:` in `config/packages/static_generator.yaml`.

```yaml
# In pushword.yaml under your app config:
pushword:
  apps:
    - host: example.tld
      static_generators: [Pushword\StaticGenerator\Generator\PagesGenerator, ...]
      static_symlink: true
      static_dir: '%kernel.project_dir%/static/{main_host}'
      static_assets: ['assets', 'bundles'] # files/folders from public/ to copy

# Or globally in config/packages/static_generator.yaml:
static_generator:
  static_generators: apache # shortcuts: apache, github, frankenphp
  static_symlink: true
  static_dir: '%kernel.project_dir%/static/{main_host}'
```

_The default generators are compatible with Apache/Litespeed and FrankenPHP/Caddy (generating .htaccess and Caddyfile)._

### `static_symlink`

Controls whether media and assets are symlinked or copied to the static output directory.

| Value | Media | Assets |
|---|---|---|
| `true` (default) | symlink | symlink |
| `false` | copy | copy |
| `['media']` | symlink | copy |
| `['assets']` | copy | symlink |
| `['media', 'assets']` | symlink | symlink |

The most common use case for the array form is `['media']`: symlink media files (fast, saves disk space) while copying assets (so they can be deployed independently).

```yaml
# Symlink media only, copy assets
static_symlink: ['media']
```

When using GitHub Pages (CNAME generator), copy is forced regardless of this setting.

### `static_assets` (formerly `static_copy`)

List of files or folders in your `public/` directory to include in the static output. Default: `['assets', 'bundles']`.

```yaml
static_assets: ['assets', 'bundles']
```

The old name `static_copy` still works as a deprecated alias.

### `static_html_max_age`

Cache TTL for HTML pages (in seconds). Default: `10800` (3 hours).

```yaml
static_html_max_age: 86400 # 24 hours
```

### `static_html_stale_while_revalidate`

Adds `stale-while-revalidate` to the `Cache-Control` header, allowing CDNs and browsers to serve stale content while revalidating in the background. Set to `0` to disable. Default: `3600` (1 hour).

```yaml
static_html_stale_while_revalidate: 0 # disabled
```

Both settings apply to HTML pages only. Static assets (images, JS, CSS, fonts) always use a 1-year TTL.

## Command

```shell
# Generate all apps
php bin/console pw:static

# Generate 1 app
php bin/console pw:static $host

# (re)Generate only one page
php bin/console pw:static $host $slug
```

## Page cache mode (serve pre-rendered pages without exporting)

Set `cache: static` on an app to pre-render pages into `public/cache/{host}/` so the web server can serve them directly without booting PHP. Unlike the full static export, the application keeps running and invalidates the cache automatically on page save.

See [Page Cache](/extension/page-cache) for setup, Caddy config, and the `pw:cache:clear` command.

## Using FrankenPHP (Caddy) to serve your static website

You must import the generated `static/example.tld/.Caddyfile` in your main Caddyfile.

If you still use the default Caddyfile (from [pushword/skeleton](https://github.com/pushword/pushword/blob/main/packages/skeleton/Caddyfile)), see the last commented part :

```Caddyfile
import static/example.tld/.Caddyfile
```