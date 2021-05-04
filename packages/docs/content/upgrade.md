---
title: Upgrade a Pushword installation
h1: Upgrade Guide
toc: true
parent: installation
---

Smooth way is to use [composer](https://getcomposer.org), a dependency manager for PHP.

Run `composer update` and the job is done (almost).

If you are doing a major upgrade, find the upgrade guide down there.

## To 0.0.728

-   Update stylesheets

```
cd assets && yarn && yarn encore production
```

-   Update doctrine

```
bin/console make:migration && bin/console doctrine:migrations:migrate
```

## From PiedWeb/CMS 0.0.86 to Pushword

-   switch dependencies from piedweb/cms to `pushword/core` (composer + src/Entity)
-   install required extension (like `pushword/admin`, have a [look in the extensions list](/extensions))
-   Delete symlinks in `config/packages`

-   Update database

    ```
    bin/console make:migration && bin/console doctrine:migrations:migrate
    ```

-   Update config by moving static under apps where first host is static.domain.

    ```
    ...
     apps:
       - {hosts: [mywebsite.com, preprod.mywebsite.com], base_url: https://mywebsite.com}
    ```

-   Delete `feed*.xml` and `sitemap*` files in `public`

-   Update your custom template file

-   Search for all `<!--` in `mainContent` and put them in `customProperties`'s textarea
