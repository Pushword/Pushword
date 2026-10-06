---
title: 'Be notify when a page is edited on your Pushword CMS'
h1: 'Page Update Notifier'
publishedAt: '2025-12-21 21:55'
toc: true
---

Emails a digest when pages are edited.

## Install

```shell
composer require pushword/page-update-notifier
```

## Configure

Per site in `config/packages/pushword.yaml`, or globally:

```yaml
page_update_notifier:
  page_update_notification_from: from@example.tld
  page_update_notification_to: me@example.tld
  page_update_notification_interval: 'PT6H' # default; a PHP DateInterval
```

## Behaviour

On every page save, unless a notification already went out within the interval, it
emails the pages edited since the previous notification (the last 30 minutes for the
first one).
