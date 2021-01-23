---
title: "Pushword Page Scanner : Find dead links, 404, 301 and more."
h1: Page Scanner
toc: true
parent: extensions
---

Find dead links, 404, 301 and more (command line or admin).

## Install

```shell
composer require pushword/page-scanner
```

That's it ! If you have a custom installation (not used the [default installer](/installation)),
you may have a look inside `vendor/pushword/admin/install.php`.

## Command

```
php bin/console pushword:page:scan $host
```
