---
h1: Installation
title: Install Pushword in a few seconds (automatic installer)
parent: homepage
toc: true
---

## Requirements

-   **PHP** >=7.4
-   **PHP extensions** : dom, curl, libxml, mbstring, zip, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv, fileinfo
-   **Composer** (#[how to install composer](https://getcomposer.org/download/))

## Automatic installer via composer

```

composer create-project pushword/new pushword

```

That's it ! You can still configure an app or directly launch a PHP Server :

```

cd pushword;

php bin/console pushword:new

php -S 127.0.0.1:8004 -t public/


```

## Next

Want more feature like **static**, **flat-file cms** or **page scanner** ?

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
