---
h1: Installation
title: Install Pushword in a few seconds (automatic installer)
parent: homepage
toc: true
---

## Requirements

-   **PHP** >=8.0
-   **PHP extensions** : dom, curl, libxml, mbstring, zip, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv, fileinfo
-   **Composer** (#[how to install composer](https://getcomposer.org/download/))

## Automatic installer via composer

```

composer create-project pushword/new pushword

```

That's it ! You can still configure an app or directly launch a PHP Server :

```shell
cd pushword;
php bin/console pushword:new
php -S 127.0.0.1:8004 -t public/

```

## _Recommended Extesions_ to get Pushword Classic

By running the following command, it will install a few extensions to have a **classic** installation.

```shell
composer req pushword/admin pushword/admin-block-editor pushword/page-scanner pushword/static-generator pushword/template-editor pushword/version

# More specific
composer req pushword/page-update-notifier
composer req pushword/advanced-main-image
composer req pushword/conversation

# create a new user
php bin/console pushword:user:create
```

## Next

Want more feature like an [admin dashboard](/extension/admin), a [static website generator](/extension/static-generator) or a
[flat-file CMS manager](/extension/flat) ?

Look the [extensions](/extensions).

## Manual installation

You can use `composer require pushword/core` in an existing Symfony Project. Have a look into `vendor/pushword/core/install.php` to finish manually the installation.

## Update

Stay up to date with only one command :

```
composer update
```

<!-- for postcss... -->
<pre style="display:none"><code>...</code></pre>
