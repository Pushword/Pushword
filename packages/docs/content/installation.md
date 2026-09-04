---
title: 'Install Pushword in a few seconds (automatic installer)'
h1: Installation
publishedAt: '2025-12-21 21:55'
toc: true
---

## Requirements

- **PHP** >=8.4
- **PHP extensions** : dom, curl, libxml, mbstring, zip, pdo, bcmath, intl, gd (or imagick), exif, iconv, fileinfo; plus `sqlite` and `pdo_sqlite` for SQLite, or `pdo_pgsql` for PostgreSQL
- **Composer** - [how to install composer](https://getcomposer.org/download/)

_Facultative_ :

- **Node** (>= 24 - only tested with v24, see [nvm to easily install a node version up to date](https://github.com/nvm-sh/nvm))
- **yarn** - [how to install yarn](https://classic.yarnpkg.com/lang/en/docs/install/#debian-stable) or _pnpm_, _npm_
  **Node** and **Yarn** are not required if you have your custom logic to manage assets.
- **libvips** (recommended) or **imagick** — see [Image Processing](#image-processing) below
- **brotli**

## Automatic installer via composer

```shell
composer create-project pushword/new pushword "^1.0.0-rc"
cd pushword
```

The `"^1.0.0-rc"` is required: Pushword is still tagged as release candidates, and
`create-project` picks the newest *stable* version by default — which, for
`pushword/new`, is a long-abandoned 0.x tag. Without the constraint you get a 2024
template that installs the wrong Symfony and a mismatched set of Pushword packages.

The installer creates the database and the demo content, then asks for the account
you will log in with — email, password, role (`ROLE_SUPER_ADMIN` by default).

SQLite is the default. To create a new project directly on PostgreSQL, create the
database first and pass its URL to the installer:

```shell
PUSHWORD_DATABASE_URL='postgresql://pushword:secret@127.0.0.1:5432/pushword?serverVersion=17&charset=utf8' \
  composer create-project pushword/new pushword "^1.0.0-rc"
```

For an existing project, set `DATABASE_URL` in `.env.local`, then run
`php bin/console doctrine:schema:update --force`. This creates or updates the schema;
it does not transfer data from an existing SQLite database.

Run unattended (CI, a provisioning script, `composer --no-interaction`), it cannot
ask. In production it creates `admin@example.tld` with a random temporary password,
prints that password once, and requires it to be changed before the account can access
administration. A development install keeps the convenient
`admin@example.tld` / `p@ssword` login; never expose those credentials in production.

That's it ! You can still configure an app or directly launch a PHP Server :

```shell
php bin/console pw:new
php -S 127.0.0.1:8004 -t public/
# OR symfony server:start -d
```

### Run Pushword with Franken PHP

1. get the last bin from [frankenphp's repositories](https://github.com/dunglas/frankenphp)
2. Create your own Caddyfile, or just [copy this one](https://github.com/Pushword/Pushword/blob/main/packages/dev-app/Caddyfile)
3. run it ➜ `php Caddy.php` or `frankenphp run --config Caddyfile`

The first available port will be used automatically (like `symfony server:start`).

FrankenPHP can also run in **worker mode** (a long-running kernel). Pushword is safe
to run this way — see [Performance](/performance) for how to enable it and why.

#### Available commands:

```shell
php Caddy.php start    # Start the server
php Caddy.php stop     # Stop the server
php Caddy.php restart  # Restart the server
php Caddy.php status   # Show server status
```

### Run Pushword with Docker

The installer checks which of the extensions above your PHP actually has, and — if a
Docker daemon is answering — asks once whether to use Docker, recommending the answer
that fits your machine. Answer no and no Docker file is written.

The image is FrankenPHP with every extension already in place, so it is the shortest
path when installing them yourself is the hard part. See [Docker](/docker) for the
development and production stacks, and for what has to live in a volume.

```shell
php bin/console pw:docker:init   # if you said no, or want them added later
docker compose up --build
```

## _Recommended Extensions_ to get Pushword Classic

`composer create-project` already gives you the **classic** set: `admin`,
`admin-block-editor`, `page-scanner`, `static-generator` and `template-editor`.

Add the rest as you need them:

```shell
composer req pushword/version              # page history and diffs
composer req pushword/search               # SQLite full-text search
composer req pushword/flat                 # content as Markdown files in Git
composer req pushword/conversation         # comments, contact & newsletter forms
composer req pushword/advanced-main-image  # hero images
composer req pushword/page-update-notifier # email alert when a page changes
```

Each one registers its own routes and config on install — nothing to wire by hand.
The Search extension keeps its own rebuildable SQLite index even when Doctrine uses
PostgreSQL, so it still requires `pdo_sqlite`.

## Image Processing

Pushword auto-detects the best available image driver in this order: **VIPS** > **Imagick** > **GD**.

### VIPS (recommended)

[libvips](https://www.libvips.org/) is ~4x faster and uses ~10x less memory than Imagick. It is the recommended driver for production.

```shell
# Install libvips system library
sudo apt install libvips-dev  # Debian/Ubuntu
# or: brew install vips        # macOS

# Install the PHP driver
composer require intervention/image-driver-vips
```

Requires the PHP **FFI** extension (`php-ffi`).

You can force a specific driver in `config/packages/pushword.yaml`:

```yaml
pushword:
    image_driver: vips  # auto (default), vips, imagick, gd
```

### Imagick

```shell
sudo apt install php-imagick  # Debian/Ubuntu
```

### GD

GD is the fallback driver, bundled with PHP (`php-gd`). No extra installation needed.

## Next

- Review the [security model and production checklist](/security).
- Configure [authentication](/authentication) (OAuth with Google/Microsoft, magic links, user management)
- Configure the [colors and display](/themes) (also see [automatic tailwind run after page update](/manage-assets)).
- Supercharge Pushword with [extensions](/extensions) or **custom development**

{{ snippet('pro-support') }}

## Manual installation

You can use `composer require pushword/core` in an existing Symfony Project. Have a look into `vendor/pushword/core/install.php` to finish manually the installation.

## Update

Stay up to date with only one command :

```shell
composer update
```
