---
title: 'Install Pushword in a few seconds (automatic installer)'
h1: Installation
publishedAt: '2025-12-21 21:55'
parentPage: search.json
toc: true
---

## Requirements

- **PHP** >=8.4
- **PHP extensions** : dom, curl, libxml, mbstring, zip, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd (or imagick), exif, iconv, fileinfo
- **Composer** - [how to install composer](https://getcomposer.org/download/)

_Facultative_ :

- **Node** (>= 24 - only tested with v24, see [nvm to easily install a node version up to date](https://github.com/nvm-sh/nvm))
- **yarn** - [how to install yarn](https://classic.yarnpkg.com/lang/en/docs/install/#debian-stable) or _pnpm_, _npm_
  **Node** and **Yarn** are not required if you have your custom logic to manage assets.
- **avifenc** (`libavif-bin` package on Debian/Ubuntu) - see [AVIF Encoding Requirements](/configuration#avif-encoding-requirements)
- **imagick**
- **brotli**

## Automatic installer via composer

```shell
composer create-project pushword/new pushword @dev

# Create the admin user
cd pushword && php bin/console pw:user:create
```

That's it ! You can still configure an app or directly launch a PHP Server :

```shell
php bin/console pw:new
php -S 127.0.0.1:8004 -t public/
# OR symfony server:start -d
```

### Run Pushword with Franken PHP

1. get the last bin from [frankenphp's repositories](https://github.com/dunglas/frankenphp)
2. Create your own Caddyfile, or just [copy this one](https://github.com/Pushword/Pushword/blob/main/packages/skeleton/Caddyfile)
3. run it ➜ `php Caddy.php` or `frankenphp run --config Caddyfile`

The first available port will be used automatically (like `symfony server:start`).

#### Available commands:

```shell
php Caddy.php start    # Start the server
php Caddy.php stop     # Stop the server
php Caddy.php restart  # Restart the server
php Caddy.php status   # Show server status
```

## _Recommended Extensions_ to get Pushword Classic

By running the following command, it will install a few extensions to have a **classic** installation.

```shell
composer req pushword/admin pushword/admin-block-editor pushword/page-scanner pushword/static-generator pushword/template-editor pushword/version

# More specific
composer req pushword/page-update-notifier
composer req pushword/advanced-main-image
composer req pushword/conversation

```

## Next

- Configure the [colors and display](/themes) (also see [automatic tailwind run after page update](/manage-assets)).
- Supercharge Pushword with [extensions](/extensions) or **custom development**

## Manual installation

You can use `composer require pushword/core` in an existing Symfony Project. Have a look into `vendor/pushword/core/install.php` to finish manually the installation.

## Update

Stay up to date with only one command :

```shell
composer update
```