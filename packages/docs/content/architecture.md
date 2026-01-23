---
title: 'Puswhord Code Architecture and preparing a development environment'
h1: 'The Code Architecture'
publishedAt: '2025-12-21 21:55'
toc: true
---

If you are searching for :

- organizing your own app code, see #[symfony good practices](https://symfony.com/doc/current/best_practices.html) or look at the #[demo app](https://github.com/Pushword/Pushword/tree/main/packages/skeleton)
- organizing the code for a pushword extension : see [create an extension](/create-extension)

Else, you are at the good place.

Here, we will speak about :

- code organisation for Pushword core and officially maintained extensions
- how to prepare a development environment [to be able to contribue](/contribute)

## Code Architecture

The code for all officially maintained extension and the core is kept in an unique repository.

It's a [mono-repository](https://tomasvotruba.com/blog/2019/10/28/all-you-always-wanted-to-know-about-monorepo-but-were-afraid-to-ask/).

It's kind of [majestic monolith](https://m.signalvnoise.com/the-majestic-monolith/).

The [core](https://github.com/Pushword/Pushword/tree/main/packages/core) contain the minimum features, then everything is done via extensions.

The core code follow as much as it can the #[symfony good practices](https://symfony.com/doc/current/best_practices.html) and have a special folder named `component` for bigger features like the [Entity Filter](/component/entity-filter) system if they do not have their own independent package.

Each extension are facultative.

Keeping all this extensions in one repository permit to test everything is working easily, to understand the code faster and to refactor much quicker.

The [skeleton](https://github.com/Pushword/Pushword/tree/main/packages/skeleton) isn't a real skeleton (copy and install).

It's used for testing, demo, using for generating the docs and a few class from skeleton are extracted by the default installer.

## On top of Symfony

Each package (except _skeleton_, _installer_ and _js-helper_) is built as a [symfony bundle](https://symfony.com/doc/current/bundles.html).

The `core` package required a symfony app instaled to be functionnal.

Want a particular details about the way the code is organized ?

#[Feel free to ask](https://github.com/Pushword/Pushword/issues/new), I will list answers here.

## Development environement

This is only for [contribution](/contribute), if you are searching to developp a new application with Pushword, see [installation](/installation).

1. Check you have installed all the [required dependencies](/installation).

2. (Fork and) Clone the #[repository](https://github.com/Pushword/Pushword)

3. Install dependencies and initialize default app

```shell
composer update && composer reset-skeleton
```

### Useful commands

```shell
# php-cs-fixer
composer format

# run rector, format and tests
composer rector

# run phpstan
composer stan

# to play with default app console (skeleton)
composer console ...
```