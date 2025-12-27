---
title: 'Be notify when a page is edited on your Pushword CMS'
h1: 'Page Update Notifier'
id: 30
publishedAt: '2025-12-21 21:55'
parentPage: extensions
toc: true
---

Get mail notification when your pushword content (page) is edited.

## Install

```
composer require pushword/page-update-notifier
```

## Configure

Add in your current `config/package/pushword.yaml` for an App or globally under `pushword_page_update_notifier:`

```
    page_update_notification_from: fromMe@example.tld
    page_update_notification_to: NotificationForMe@example.tld
    page_update_notification_interval: 'P1D' #See PHP DateInterval format https://www.php.net/manual/fr/class.dateinterval.php
```

## Usage

Nothing to do, just get notify. On postPersist/postUpdate, the extension check if you didn't get notify till too long (`interval`), then, send the last edit since `interval`.