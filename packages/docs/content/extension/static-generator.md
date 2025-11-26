---
title: Static Website Generator with Pushword CMS
h1: Static Generator
toc: true
parent: extensions
---

Generate a static website serve by github pages, apaches with one command or via the [admin](https://pushword.piedweb.com/extension/admin).

## Install

```shell
composer require pushword/static-generator
```

That's it ! If you have a custom installation (not used the [default installer](/installation)),
you may have a look inside `vendor/pushword/admin/install.php`.

## Configure

Add in your current `config/package/pushword.yaml` for an App or globally under `pushword_static_generator:`

```yaml
pushword:
  # ...
  static_generators: apache|github|frankenphp|[..., classNameGenerator, ...]
  static_symlink: true
  static_dir: '' #default /static/%mainHost.tld%/
```

_The default generators are compatible with Apache/Litespeed and FrankenPHP/Caddy (generating .htaccess and Caddyfile)._

## Command

```shell
# Generate all apps
php bin/console pw:static:generate

# Generate 1 app
php bin/console pw:static:generate $host

# (re)Generate only one page
php bin/console pw:static:generate $host $slug
```

## Using FrankenPHP (Caddy) to serve your static website

You must import the generated `static/example.tld/.Caddyfile` in your main Caddyfile.

If you still use the default Caddyfile (from [pushword/skeleton](https://github.com/pushword/pushword/blob/main/packages/skeleton/Caddyfile)), see the last commented part :

```Caddyfile
import static/example.tld/.Caddyfile
```
