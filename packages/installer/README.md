# Pushword Installer

Install Pushword and its packages in minutes — automatic per-package install hooks.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2FPushword%2FPushword%2Fbadges%2Fcoverage.json)](https://github.com/Pushword/Pushword/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/installer.svg?style=flat)](https://packagist.org/packages/pushword/installer)

## Features

- **Automatic package setup** — runs each package's `install.php` on `composer require`.
- Stays installed to support **future package installs**.

## How It Works

`PostInstall::runPostUpdate` executes each package's `install.php` when you add a Pushword
package, once per package. `pushword/core`'s script is what sets a new project up: database,
super admin, routes, assets and default config.

`pushword/installer` remains installed afterwards so later `composer require` calls get the
same treatment.

The old `src/installer` bash script (`post-install-cmd`) is a no-op since rc828 and removes
itself on the next `composer install`.

## Manual Installation

If you prefer not to use automatic installation, you can:
1. Remove `pushword/installer` from your dependencies
2. Manually follow the steps in each package's `install.php` file

## Documentation

Visit [pushword.piedweb.com/installation](https://pushword.piedweb.com/installation).

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
