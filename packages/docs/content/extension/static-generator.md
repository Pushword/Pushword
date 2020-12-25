---
title: Be notify when your Pushword installation is edited
h1: Page Update Notifier
toc: true
---

Generate a static website serve by github pages, apaches with one command or via the [admin](https://pushword.piedweb.com/extension/admin).

## Install

```shell
composer require piedweb/template-editor
```

Add Routes

```yaml
static:
  resource: '@PushwordStaticGeneratorBundle/StaticRoutes.yaml'
```

## Configure

Add in your current `config/package/pushword.yaml` for an App or globally under `pushword_static_generator:`

```
    static_generate_for: apache|github
    static_symlink: true
    static_dir: '' #default /%mainHost.tld%/
```

## Command

```
php bin/console pushword:static:generate $host
```

Where $host is facultative.
