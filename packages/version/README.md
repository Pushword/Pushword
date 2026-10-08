# Pushword Version

**Version** your Pushword pages (and snippets) — list, compare side-by-side and restore previous revisions.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2FPushword%2FPushword%2Fbadges%2Fcoverage.json)](https://github.com/Pushword/Pushword/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/version.svg?style=flat)](https://packagist.org/packages/pushword/version)

## Features

- **Automatic versioning** on create/update via a Doctrine listener.
- Admin UI to **list, diff and restore** versions per page.
- **Snippet versioning** when [pushword/snippet](https://github.com/Pushword/snippet) is installed.
- Works alongside [Flat](https://github.com/Pushword/flat) / Git.

## Installation

```shell
composer require pushword/version
```

Then register the routes:

```yaml
pushword_version:
  resource: '@PushwordVersionBundle/VersionRoutes.yaml'
```

## Documentation

Visit [pushword.piedweb.com/extension/version](https://pushword.piedweb.com/extension/version).

## The Pushword ecosystem

Pushword is a modular CMS — one [Symfony](https://symfony.com) bundle for the core and one bundle per feature. See the full list and guides at [pushword.piedweb.com/extensions](https://pushword.piedweb.com/extensions).

## Contributing

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Pushword/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
