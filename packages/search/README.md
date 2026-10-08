# Pushword Search

Optional **SQLite-native full-text search** for [Pushword](https://pushword.piedweb.com), powered by [Loupe](https://github.com/loupe-php/loupe) — typo tolerance, stemming and ranking with **zero infrastructure**.

## Features

- **Full-text search**: typo tolerance, stemming, ranking — no Elasticsearch, no daemon.
- **One index per host**, built from published pages.
- **Incremental reindex** on save/delete, plus on-demand/cron `pw:search:index`.
- Portable SQLite index that **ships with the static build**.

## Installation

```shell
composer require pushword/search
php bin/console pw:search:index   # build one index per host
```

Then browse to `/search?q=…` (dynamic) or ship the generated index with `pw:static` (static).

## Documentation

See [pushword.piedweb.com/extension/search](https://pushword.piedweb.com/extension/search).

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
