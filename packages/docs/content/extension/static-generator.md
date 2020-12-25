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

```
    static_generators: apache|github|[..., classNameGenerator, ...]
    static_symlink: true
    static_dir: '' #default /%mainHost.tld%/
```

## Command

```
# Generate all apps
php bin/console pushword:static:generate

# Generate 1 app
php bin/console pushword:static:generate $host

# (re)Generate only one page
php bin/console pushword:static:generate $host $slug
```
