---
title: 'Be notify when a page is edited on your Pushword CMS'
h1: 'Page Update Notifier'
publishedAt: '2025-12-21 21:55'
toc: true
---

Get mail notification when your pushword content (page) is edited.

## Install

```
composer require pushword/page-update-notifier
```

## Configure

Add in your current `config/packages/pushword.yaml` for an App or globally under `page_update_notifier:` in `config/packages/page_update_notifier.yaml`.

```yaml
page_update_notifier:
  page_update_notification_from: fromMe@example.tld
  page_update_notification_to: NotificationForMe@example.tld
  page_update_notification_interval: 'PT6H' # Default: 6 hours. See PHP DateInterval format https://www.php.net/manual/fr/class.dateinterval.php
```

## Usage

Nothing to do, just get notified. On postPersist/postUpdate, the extension checks if you haven't been notified within the `interval`, then sends a notification listing pages edited in the last 30 minutes.