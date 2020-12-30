---
title: Puswhord Code Architecture
h1: The Code Architecture
toc: true
parent: contribute
---

Are you searching for :

- organizing your own app code
- organizing the code for a pushword extension

Else, you are at the good place.

The code for all officially maintained extension and the core is kept in an unique repository adopting the [mono-repository](https://tomasvotruba.com/blog/2019/10/28/all-you-always-wanted-to-know-about-monorepo-but-were-afraid-to-ask/).

The [core](https://github.com/Pushword/Pushword/tree/main/packages/core/) contain the minimum features, then everything is done via extension.

Each extension are facultative.

Keeping all this extensions in one repository permit to test them easily and to code much quicker.

The [skeleton](https://github.com/Pushword/Pushword/tree/main/packages/skeleton) isn't a real skeleton (copy and install). It's used for testing, demos and a few class from skeleton are extracted by the default installer.

## On top of Symfony

Each package (except _skeleton_) is build as a [symfony bundle](https://symfony.com/doc/current/bundles.html).

The `core` package required a symfony app instaled to be functionnel.

When you know that, you just have to learn how to make a bundle for symfony and you will know how to make an extension for pushword.

There is some simple example like [flat](https://github.com/Pushword/Pushword/tree/main/packages/flat/) or more complex like [conversation](https://github.com/Pushword/Pushword/tree/main/packages/conversation/) (and more respecting the symfony best practices).

Learn more about [create an extension for Puswhord](/create-extension)
