---
title: Puswhord Flat File CMS - Markdown and Twig Ready
h1: Flat
toc: true
parent: extensions
---

Transform Pushword in a FlatFile CMS.

## Install

```
composer require piedweb/flat-file
```

## Configure

Add in your current `config/package/pushword.yaml` for an App or globally under `pushword_static_generator:`

```
    flat_content_dir: content #default value
```

## Usage

### Command Line

```
php bin/console pushword:flat:import $host
```

Where $host is facultative.

```

```
