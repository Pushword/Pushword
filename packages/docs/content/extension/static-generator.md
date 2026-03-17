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

## Command

```shell
# Generate all apps
php bin/console pw:static

# Generate 1 app
php bin/console pw:static $host

# (re)Generate only one page
php bin/console pw:static $host $slug
```

## Using FrankenPHP (Caddy) to serve your static website

You must import the generated `static/example.tld/.Caddyfile` in your main Caddyfile.

If you still use the default Caddyfile (from [pushword/skeleton](https://github.com/pushword/pushword/blob/main/packages/skeleton/Caddyfile)), see the last commented part :

```Caddyfile
import static/example.tld/.Caddyfile
```