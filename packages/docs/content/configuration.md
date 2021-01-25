---
title: Configure a fresh install of Pushword
h1: Configuration
toc: true
parent: installation
---

If you use the automatic installer, just open `config/packages/pushword.yaml` and start configure your app(s).

The only important property to configure now is **host** (`host: localhost.dev`). Then you are ready to use your website.

To get the up to date configuration's options :

```shell
php bin/console config:dump-reference PushwordCoreBundle
```

If you don't understand what the purpose of `assets`, `filters` or another config's property. Feel free to ask (github or mail), I will list answers on this page.
