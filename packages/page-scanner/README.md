# Pushword Page Scanner

Find **dead links, 404s, 301s**, broken anchors/media and **TODO reminders** across your content — and audit your **internal link graph** — from the command line or the admin.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2FPushword%2FPushword%2Fbadges%2Fcoverage.json)](https://github.com/Pushword/Pushword/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/page-scanner.svg?style=flat)](https://packagist.org/packages/pushword/page-scanner)

## Features

- Checks **internal links, external HTTP status, anchors, media and parent pages**.
- **`pw:page-scan` CLI** + admin UI with cached results.
- **`pw:link:graph` CLI** — inbound/outbound links, depth from the homepage, orphans; exits non-zero to gate CI.
- **TODO comments** (`linkWhenPublished`, `doWhenPublished`).
- **Parallel** external checks with caching.

## Installation

```shell
composer require pushword/page-scanner
```

## Documentation

Visit [pushword.piedweb.com/extension/page-scanner](https://pushword.piedweb.com/extension/page-scanner).

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
