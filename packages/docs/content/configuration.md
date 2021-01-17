---
title: Configure a fresh install of Pushword
h1: Configuration
toc: true
parent: installation
---

If you use the automatic installer, just open `config/packages/pushword.yaml` and start configure your app(s).

The only important property to configure now is **hosts** (`hosts: [localhost.dev]`). Then you are ready to use your website.

To get the up to date configuration's options :

```shell
php bin/console config:dump-reference PushwordCoreBundle
```
