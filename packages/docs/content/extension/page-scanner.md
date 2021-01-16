---
title: 'Pushword Page Scanner : Find dead links, 404, 301 and more.'
h1: Page Scanner
toc: true
parent: extensions
---

Find dead links, 404, 301 and more (command line or admin).

## Install

```shell
composer require pushword/page-scanner
```

Add Routes

```yaml
page_scanner:
  resource: '@PushwordPageScannerBundle/PageScannerRoutes.yaml'
# or do it in 1 command line
# $ sed -i '1s/^/page_scanner:\n    resource: "@PushwordPageScannerBundle\/PageScannerRoutes.yaml"\n/' config/routes.yaml
```

## Command

```
php bin/console pushword:page:scan $host
```
