# Pushword

A **Symfony CMS** for managing multiple sites and languages from one installation.
Edit pages in the **admin**, in **Git**, or with an **AI agent**.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)
[![Code Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2FPushword%2FPushword%2Fbadges%2Fcoverage.json)](https://github.com/Pushword/Pushword/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/core.svg?style=flat)](https://packagist.org/packages/pushword/core)

## Quickstart

You need PHP 8.5, Composer and the [required PHP extensions](https://pushword.piedweb.com/installation#requirements).

```shell
composer create-project pushword/new pushword "^1.0"
cd pushword
php -S 127.0.0.1:8004 -t public/
```

The installer sets up SQLite by default and adds demo content. Open the [demo site](http://127.0.0.1:8004/)
and sign in to [the admin](http://127.0.0.1:8004/admin) with the account you create during installation.
For Docker and FrankenPHP setup, see the [installation guide](https://pushword.piedweb.com/installation).

![A page open in the Pushword admin block editor](packages/docs/content/media/admin-blockeditor.png)

## Why Pushword

- **Edit in the admin.** Write with the block editor and manage pages and media across sites. Pages use Markdown with YAML front matter, and layouts use Twig.
- **Review content changes in Git.** Flat syncs Markdown files with the database so you can work on the same pages in Git and in the admin. [Flat documentation](https://pushword.piedweb.com/extension/flat)
- **Edit with an AI agent.** Agents can work on local Markdown files or edit a live site through the token-authenticated REST API. Page revisions protect against overwriting concurrent edits. [AI agent guide](https://pushword.piedweb.com/ai-agents)
- **Export a static site.** Publish to GitHub Pages or serve the exported files with Apache or FrankenPHP. [Static Generator documentation](https://pushword.piedweb.com/extension/static-generator)
- **Extend it in Symfony.** Build on events, entity filters and Twig components.

## Extensions

The `pushword/new` starter includes the admin and block editor, Flat, the API, static export and page revision history.
Add optional bundles such as Search, Newsletter or Quiz individually with Composer. [Browse all extensions](https://pushword.piedweb.com/extensions)

To install all maintained bundles together on an existing Pushword site, use the aggregate package.

```shell
composer require "pushword/pushword:^1.0"
```

## Documentation

Visit [pushword.piedweb.com](https://pushword.piedweb.com)

## Contributing

This repository is the monorepo: every bundle lives in `packages/` and is split
read-only to its own repository for distribution. Issues and pull requests belong
**here**.

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

If Pushword is useful to you, starring the repository is the simplest way to help others find it.
You can also support its maintenance on [Liberapay](https://liberapay.com/RobinPiedWeb).

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Pushword/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
