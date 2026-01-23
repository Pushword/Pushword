# Puswhord Installer

Shell Script to install Puswhord in a minut.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://codecov.io/gh/Pushword/Pushword/branch/main/graph/badge.svg)](https://codecov.io/gh/Pushword/Pushword/tree/main)
[![Type Coverage](https://shepherd.dev/github/pushword/pushword/coverage.svg)](https://shepherd.dev/github/pushword/pushword)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/core.svg?style=flat)](https://packagist.org/packages/pushword/core)

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

Visit [pushword.piedweb.com](https://pushword.piedweb.com/installation)

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
