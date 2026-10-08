# Pushword Admin

Default admin interface for [Pushword](https://pushword.piedweb.com) — manage pages, media and users for multi-site, multi-user setups, built on top of EasyAdmin.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2FPushword%2FPushword%2Fbadges%2Fcoverage.json)](https://github.com/Pushword/Pushword/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/admin.svg?style=flat)](https://packagist.org/packages/pushword/admin)

## Features

- CRUD for **Page, Media and User** on top of [EasyAdmin](https://easyadmin.com).
- Page/User **form fields configurable** straight from your YAML config.
- **Customizable admin menu**, multi-site and multi-user aware.
- Plays well with the [block editor](https://github.com/Pushword/admin-block-editor), [template editor](https://github.com/Pushword/template-editor), [versioning](https://github.com/Pushword/version) and more.

## Installation

```shell
composer require pushword/admin
php bin/console pw:user:create   # create a ROLE_SUPER_ADMIN to log in
```

Admin is then available at `https://your-domain.tld/admin/`.

## Documentation

Visit [pushword.piedweb.com/extension/admin](https://pushword.piedweb.com/extension/admin).

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
