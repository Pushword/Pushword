# Pushword Installer

Install Pushword and its packages in minutes — an interactive setup script plus automatic per-package install hooks.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://codecov.io/gh/Pushword/Pushword/branch/main/graph/badge.svg)](https://codecov.io/gh/Pushword/Pushword/tree/main)
[![Type Coverage](https://shepherd.dev/github/pushword/pushword/coverage.svg)](https://shepherd.dev/github/pushword/pushword)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/core.svg?style=flat)](https://packagist.org/packages/pushword/core)

## Features

- **Interactive setup** during `composer create-project` (database, admin user, routes, assets).
- **Automatic package setup** — runs each package's `install.php` on `composer require`.
- Stays installed to support **future package installs**.

## How It Works

This package provides two mechanisms:

1. **Interactive Setup** (`src/installer`): A bash script that runs once during `composer create-project` to set up a new Pushword project (database, admin user, routes, assets).

2. **Automatic Package Setup** (`PostInstall::runPostUpdate`): Automatically executes each package's `install.php` when you add new Pushword packages via `composer require`.

After initial setup, `pushword/installer` remains installed to support future package installations. Only the interactive bash script reference is removed from `post-install-cmd`.

## Manual Installation

If you prefer not to use automatic installation, you can:
1. Remove `pushword/installer` from your dependencies
2. Manually follow the steps in each package's `install.php` file

## Documentation

Visit [pushword.piedweb.com/installation](https://pushword.piedweb.com/installation).

## The Pushword ecosystem

Pushword is a modular CMS — one [Symfony](https://symfony.com) bundle for the core and one bundle per feature. Pick only what you need:

**Core**
- [pushword/core](https://github.com/Pushword/core) — Symfony-based CMS core: Page, Media & User entities, Markdown + Twig rendering.

**Editing & admin**
- [pushword/admin](https://github.com/Pushword/admin) — EasyAdmin interface to manage pages, media and users.
- [pushword/admin-block-editor](https://github.com/Pushword/admin-block-editor) — Gutenberg-like block editor (stores Markdown).
- [pushword/advanced-main-image](https://github.com/Pushword/advanced-main-image) — Hero images & main-image format control.
- [pushword/template-editor](https://github.com/Pushword/template-editor) — Edit Twig templates online.
- [pushword/snippet](https://github.com/Pushword/snippet) — Reusable content fragments & components.

**Content & workflow**
- [pushword/flat](https://github.com/Pushword/flat) — Flat-file (Markdown + Git) CMS mode.
- [pushword/version](https://github.com/Pushword/version) — Page & snippet versioning.
- [pushword/page-update-notifier](https://github.com/Pushword/page-update-notifier) — Email alerts on content changes.
- [pushword/conversation](https://github.com/Pushword/conversation) — Comments, contact & newsletter forms.

**Publishing & performance**
- [pushword/static-generator](https://github.com/Pushword/static-generator) — Export a static website (GitHub Pages, Apache, FrankenPHP).
- [pushword/search](https://github.com/Pushword/search) — SQLite full-text search (Loupe), zero infra.
- [pushword/page-scanner](https://github.com/Pushword/page-scanner) — Find dead links, 404s, redirects & TODOs.
- [pushword/api](https://github.com/Pushword/api) — Token-authenticated REST API.

**Tooling**
- **pushword/installer** — Project & package installer. *(this package)*
- [pushword/js-helper](https://github.com/Pushword/js-helper) — Front-end JavaScript helpers.

Full list and guides on [pushword.piedweb.com/extensions](https://pushword.piedweb.com/extensions).

## Contributing

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Core/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license#license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
