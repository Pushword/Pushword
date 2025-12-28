---
title: 'Pushword Page Scanner : Find dead links, 404, 301 and more.'
h1: 'Page Scanner'
id: 31
publishedAt: '2025-12-21 21:55'
parentPage: extensions
toc: true
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
php bin/console pw:page-scan $host
```

## Configuration

You can configure to avoid to check some links based on a url pattern (with wildcard).

In a config file `pushword_page_scanner.yaml` :

```yaml
pushword_page_scanner:
    links_to_ignore:
        - https://example.tld/*
```

In a page (_customPropertiy_) :

```yaml
pageScanLinksToIgnore:
    - https://example.tld/*
```