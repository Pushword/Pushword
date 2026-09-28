---
title: 'Contribute to Pushword documentation, core or extensions'
h1: Contribute
publishedAt: '2025-12-21 21:55'
toc: true
---

The source code is on [GitHub](https://github.com/Pushword/Pushword).

Looking for help with your own site rather than to contribute? See [getting help](/pro).

## Report an issue

Use the [issue tracker](https://github.com/Pushword/Pushword/issues).

## Contribute

Send contributions as [pull requests](https://github.com/Pushword/Pushword/pulls). See
[Code architecture](/architecture) for the monorepo setup.

## Setting up a PHP development environment to contribute

See [Code Architecture > Development environment](/architecture#development-environment)

## Contribute to the documentation

Documentation is Markdown under
[`packages/docs/content`](https://github.com/Pushword/Pushword/tree/main/packages/docs/content).

On each push to `main`, GitHub Actions builds and publishes the documentation on
[pushword.piedweb.com](/). A rendering failure stops publication.

## Pull Requests

### New Features

Prefer a dedicated extension for a self-contained feature. Propose it for the monorepo
when it serves a broad need and benefits from cross-package testing; otherwise maintain
it separately and submit a documentation link once it is tested and usable.

### Coding standards

Before opening a pull request, run the formatter, Rector and PHPStan:

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

With MariaDB and PostgreSQL listening on the test URLs above, run:

```shell
composer bench-databases
```

See [Database and pipeline benchmarks](/database-benchmarks) for the workloads,
environment variables, methodology and dated reference results.

### Coverage

```
composer test-coverage
```

Writes `coverage/index.html` and `coverage.xml`. It needs the `pcov` extension
(`dnf install php-pecl-pcov`, `apt install php-pcov`, …); pcov ships disabled, but the
script enables it per run, so no php.ini change is required. Like CI, it runs the suite
in three batches — parallel, serial, worker — and merges their reports, so a batch left
out never silently reads as uncovered.

### Pull request scope

- Add tests for changed behavior.
- Update the documentation and upgrade note when required.
- Preserve [semantic versioning](https://semver.org/) and avoid unrelated changes.
