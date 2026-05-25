# Pushword Search

Optional SQLite-native full-text search for [Pushword](https://pushword.piedweb.com),
powered by [Loupe](https://github.com/loupe-php/loupe). Typo tolerance, stemming and
ranking with **zero infrastructure** — no Elasticsearch, no daemon — and an index
that ships with the static build.

```shell
composer require pushword/search
php bin/console pw:search:index   # build one index per host
```

Then browse to `/search?q=…` (dynamic sites) or ship the generated `search.json` /
Loupe index with `pw:static` (static sites).

See the [documentation](https://pushword.piedweb.com/extension/search).
