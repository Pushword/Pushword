---
title: Be notify when a page is edited on your Pushword
h1: Page Update Notifier
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
    notifier_email: fromMe@example.tld
    page_update_notification_mail: NotificationForMe@example.tld
    interval: 'P1D' #See PHP DateInterval format https://www.php.net/manual/fr/class.dateinterval.php
```

## Usage

Nothing to do, just get notify. On postPersist/postUpdate, the extension check if you didn't get notify till too long (`interval`), then, send the last edit since `interval`.
