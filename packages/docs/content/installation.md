---
title: 'Install Pushword in a few seconds (automatic installer)'
h1: Installation
publishedAt: '2025-12-21 21:55'
toc: true
---

## Requirements

- **PHP** 8.5
- **PHP extensions**: dom, curl, libxml, mbstring, zip, pdo, bcmath, intl, gd, exif, iconv, fileinfo; plus `sqlite` and `pdo_sqlite` for SQLite, or `pdo_pgsql` for PostgreSQL
- **Composer** — [installation instructions](https://getcomposer.org/download/)

Optional:

- **Node 24+** and npm, pnpm or Yarn, only when building frontend assets
- **libvips** (recommended) or **imagick** — see [Image Processing](#image-processing) below
- **brotli**

## Automatic installer via composer

```shell
composer create-project pushword/new pushword "^1.0"
cd pushword
```

`"^1.0"` keeps the project on the stable 1.x line. In an existing application,
constrain every `pushword/*` dependency, including `pushword/installer`, to `^1` too.

The installer creates the database and the demo content, then asks for the account
you will log in with — email, password, role (`ROLE_SUPER_ADMIN` by default).

SQLite is the default. To create a new project directly on PostgreSQL, create the
database first and pass its URL to the installer:

```shell
PUSHWORD_DATABASE_URL='postgresql://pushword:secret@127.0.0.1:5432/pushword?serverVersion=17&charset=utf8' \
  composer create-project pushword/new pushword "^1.0"
```

For an existing project, set `DATABASE_URL` in `.env.local`, then run
`php bin/console doctrine:schema:update --force`. This creates or updates the schema;
it does not transfer data from an existing SQLite database.

Unattended (CI, a provisioning script, `composer --no-interaction`), it creates
`admin@example.tld` instead: in production with a random temporary password, printed
once and to be changed before administration can be accessed; in development with
`p@ssword` — never expose those credentials in production.

Add another site or start a development server:

```shell
php bin/console pw:new
php -S 127.0.0.1:8004 -t public/
# or: symfony server:start -d
```

### Run Pushword with FrankenPHP

The installer writes the project's `Caddyfile`. Install
[FrankenPHP](https://frankenphp.dev/docs/) and run:

```shell
frankenphp run --config Caddyfile
```

The first available port will be used automatically (like `symfony server:start`).

FrankenPHP can also run in **worker mode** (a long-running kernel). Pushword is safe
to run this way — see [Performance](/performance) for how to enable it and why.

### Run Pushword with Docker

If a Docker daemon is answering, the installer asks once whether to use Docker,
recommending an answer from the extensions your PHP actually has. Answer no and no
Docker file is written. See [Docker](/docker) for the development and production stacks.

```shell
php bin/console pw:docker:init   # if you said no, or want them added later
docker compose up --build
```

## Installed and optional extensions

`composer create-project` installs `admin`, `admin-block-editor`,
`advanced-main-image`, `api`, `conversation`, `flat`, `page-scanner`,
`static-generator`, `template-editor` and `version`.

Add other extensions as needed:

```shell
composer req pushword/search               # SQLite full-text search
composer req pushword/newsletter           # campaigns and automations
composer req pushword/quiz                 # interactive quizzes
composer req pushword/page-update-notifier # email alert when a page changes
```

Each one registers its own routes and config on install — nothing to wire by hand.

To install all maintained Pushword bundles in an existing site instead, run:

```shell
composer require "pushword/pushword:^1.0"
```

This is the combined bundle package; use `pushword/new` above to create a site.

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

GD is the required fallback driver. Install the `php-gd` package when your PHP build
does not already include it.

## Next

- Review the [security model and production checklist](/security).
- Configure [authentication](/authentication) (OAuth with Google/Microsoft, magic links, user management).
- Configure the [colors and display](/themes) (also see [automatic tailwind run after page update](/manage-assets)).
- Add [extensions](/extensions) or custom development.

{{ snippet('pro-support') }}

## Manual installation

Run `composer require pushword/core` in an existing Symfony project, then use
`vendor/pushword/core/install.php` as the integration reference.

## Update

Update dependencies with:

```shell
composer update
```
