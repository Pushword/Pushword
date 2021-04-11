---
title: Puswhord Code Architecture
h1: The Code Architecture
toc: true
parent: contribute
---

Are you searching for :

-   organizing your own app code : see #[symfony good practices](https://symfony.com/doc/current/best_practices.html) or look at the #[demo app](https://github.com/Pushword/Pushword/tree/main/packages/skeleton)
-   organizing the code for a pushword extension : see [create an extension](/create-extension)

Else, you are at the good place.

Here, we will speak about code organisation for Pushword core and officially maintained extensions.

The code for all officially maintained extension and the core is kept in an unique repository : it's a [mono-repository](https://tomasvotruba.com/blog/2019/10/28/all-you-always-wanted-to-know-about-monorepo-but-were-afraid-to-ask/). It's kind of [majestic monolith](https://m.signalvnoise.com/the-majestic-monolith/).

The [core](https://github.com/Pushword/Pushword/tree/main/packages/core) contain the minimum features, then everything is done via extension.

The core code follow as much as it can the #[symfony good practices](https://symfony.com/doc/current/best_practices.html) and have a special folder named `componenent` for bigger thing (in fact, components will may have their own package one day).

Each extension are facultative.

Keeping all this extensions in one repository permit to test them easily, to understand the code easily and to code much quicker. Before v1, it was very very cool to refactor quickly the code.

The [skeleton](https://github.com/Pushword/Pushword/tree/main/packages/skeleton) isn't a real skeleton (copy and install).

It's used for testing, demo, using for generating the docs and a few class from skeleton are extracted by the default installer.

## On top of Symfony

Each package (except _skeleton_, _installer_ and _js-helper_) is built as a [symfony bundle](https://symfony.com/doc/current/bundles.html).

The `core` package required a symfony app instaled to be functionnal.

Want a particular details about the way the code is organized ? Feel free to ask (github or mail), I will list my answer here.
