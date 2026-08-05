# Pushword

A **Symfony CMS** to rapidly create, manage and maintain your websites — from the **admin**, from **Git**, or from your **AI agent**.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)
[![Code Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2FPushword%2FPushword%2Fbadges%2Fcoverage.json)](https://github.com/Pushword/Pushword/actions/workflows/run-tests.yml)
[![Type Coverage](https://shepherd.dev/github/pushword/pushword/coverage.svg)](https://shepherd.dev/github/pushword/pushword)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/core.svg?style=flat)](https://packagist.org/packages/pushword/core)

## Why Pushword

- **Content is Markdown + Twig.** Pages are Markdown with front matter; templates are plain Twig. No proprietary field builder to learn.
- **Multi-site and multi-language** from one app and one admin.
- **SQLite by default.** No database server to provision — and no migrations to run.
- **Database or flat files.** With [Flat](https://pushword.piedweb.com/extension/flat), content lives as Markdown in Git and the admin stays usable.
- **Deployable as a static site.** [Static Generator](https://pushword.piedweb.com/extension/static-generator) exports the whole site for GitHub Pages, Apache or FrankenPHP.
- **Extensible where it matters** — events, entity filters, CommonMark extensions and Twig components, all documented.

## Installation

```shell
composer create-project pushword/new pushword "^1.0.0-rc"

cd pushword && php bin/console pw:user:create
php -S 127.0.0.1:8004 -t public/
```

Full requirements and the FrankenPHP setup are in the [installation guide](https://pushword.piedweb.com/installation).

## Extensions

Officially maintained bundles, installable one by one:

| | |
|---|---|
| [Admin](https://pushword.piedweb.com/extension/admin) | Manage pages, media and users (EasyAdmin) |
| [Flat](https://pushword.piedweb.com/extension/flat) | Turn Pushword into a flat-file, Git-based CMS |
| [API](https://pushword.piedweb.com/extension/api) | Token-authenticated REST API, OpenAPI-described |
| [Search](https://pushword.piedweb.com/extension/search) | SQLite full-text search via Loupe, zero infra |
| [Static Generator](https://pushword.piedweb.com/extension/static-generator) | Export the site as static files |
| [Page Scanner](https://pushword.piedweb.com/extension/page-scanner) | Find dead links, 404s and redirects |
| [Newsletter](https://pushword.piedweb.com/extension/newsletter) | Audiences, campaigns and automations |
| [Version](https://pushword.piedweb.com/extension/version) | Page versioning and diffs |

See [all extensions](https://pushword.piedweb.com/extensions) for the rest — block editor, snippets, quizzes, comments and more.

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

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license#license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
