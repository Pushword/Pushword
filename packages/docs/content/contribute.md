---
title: 'Contribute to Pushword : Documention, Core or Extension'
h1: Contribute
publishedAt: '2025-12-21 21:55'
toc: true
---

Source code is host on #[{{ svg('github') }} github](https://github.com/Pushword/Pushword).

Looking for help with your own site rather than to contribute? See [getting help](/pro).

## Signal an issue

Use the #[github issue tracker](https://github.com/Pushword/Pushword/issues) to signal an issue.

> This project is open source, and as such, the maintainers give their free time to build and maintain the source code
> held within. They make the code freely available in the hope that it will be of use to other developers. It would be
> extremely unfair for them to suffer abuse or anger for their hard work.

## Contribute

Contributions are **welcome**.

Please, send your contribution via a #[github pull request](https://github.com/Pushword/Pushword/pulls) on #[Pushword/Pushword](https://github.com/Pushword/Pushword).

The code is mainly organised in a mono-repo, learn more about the [code architecture](/architecture)

## Setting up a PHP development environment to contribute

See [Code Architecture > Development environment](/architecture#development-environment)

## Contribute to the documentation

The docs is inside the main repo, you will find write in markdown in #[packages/docs/content](https://github.com/Pushword/Pushword/tree/main/packages/docs/content).

On each push to `main`, a github action compiles the docs and publishes it on [pushword.piedweb.com](/). Nothing to run by hand: what fails the build (a page that does not render) fails the action, and nothing is published.

## Pull Requests

### New Features

When requesting or submitting new features, first consider to create a dedicated extension.

If your extension reply to an important community need, you can create a pull request to merge it in this Mono Repo. It will permit to maintain easily it compatibility in next Pushword update. Moreover, extension will be tested at each commit on one of Pushword's package.

Else, consider create it own git repo and create a Pull Request on the doc to add a link to this fresh extension. The link will be accepted if your extension is well tested and fully functionnal.

### Coding standards

This project respect PSR-12 Coding standard. Before your pull-request, run `php-cs-fixer` and `phpstan`.

```
composer rector
composer stan
```

### Tests

```
composer test
```

The suite runs against SQLite by default. The two server-backed variants are:

```
composer test-mariadb
composer test-postgresql
```

This requires a one-time setup of a `pushword` user owning a `pushword_test*` database
prefix (each parallel worker gets its own `pushword_test_w<n>` database):

```sql
CREATE USER 'pushword'@'%' IDENTIFIED BY 'pushword';
GRANT ALL PRIVILEGES ON `pushword\_test%`.* TO 'pushword'@'%';
```

The DSN lives in the `test-mariadb` script (`composer.json`); override it by exporting
`PUSHWORD_TEST_DATABASE_BASE_URL` before running `composer test`. The PostgreSQL role
must be allowed to create databases because each ParaTest worker gets its own:

```sql
CREATE ROLE pushword LOGIN PASSWORD 'pushword' CREATEDB;
```

### Database volume benchmark

With MariaDB and PostgreSQL listening on the test URLs above, compare the three database
engines over 100, 1,000 and 10,000 pages:

```shell
composer bench-databases
```

The benchmark reports write time plus indexed slug lookups, JSON tag filtering, numeric
JSON filtering and a sorted list. Change the volume ladder or DSNs when needed:

```shell
PUSHWORD_BENCH_VOLUMES=1000,10000,50000 \
PUSHWORD_BENCH_MYSQL_URL='mysql://…/pushword_bench?serverVersion=11.8.6-MariaDB' \
PUSHWORD_BENCH_POSTGRESQL_URL='postgresql://…/pushword_bench?serverVersion=17' \
  composer bench-databases
```

### Coverage

```
composer test-coverage
```

Writes `coverage/index.html` and `coverage.xml`. It needs the `pcov` extension
(`dnf install php-pecl-pcov`, `apt install php-pcov`, …); pcov ships disabled, but the
script enables it per run, so no php.ini change is required. Like CI, it runs the suite
in three batches — parallel, serial, worker — and merges their reports, so a batch left
out never silently reads as uncovered.

### Other Requirements

This attention would be nice :

- **Add tests**

- **Document any change in behaviour** - Make sure the [documentation](https://github.com/Pushword/Pushword/tree/main/packages/docs/content) are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

**Happy coding**!
