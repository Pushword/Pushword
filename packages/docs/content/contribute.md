---
title: 'Contribute to Pushword : Documention, Core or Extension'
h1: Contribute
publishedAt: '2025-12-21 21:55'
toc: true
---

Source code is host on #[{{ svg('github') }} github](https://github.com/Pushword/Pushword).

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

On each PR, the docs is compiled for the current release and published [pushword.piedweb.com](/) by a github action.

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

# and to test with --prefer-lowest
composer test full
```

### Other Requirements

This attention would be nice :

- **Add tests**

- **Document any change in behaviour** - Make sure the [documentation](../packages/docs/content/) are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

**Happy coding**!
