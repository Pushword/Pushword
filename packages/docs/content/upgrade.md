---
title: Upgrade a Pushword installation | Changelog
h1: Upgrade Guide
toc: true
parent: installation
---

Smooth way is to use [composer](https://getcomposer.org), a dependency manager for PHP.

Run `composer update` and the job is done (almost).

If you are doing a major upgrade, find the upgrade guide down there.

## To next !

- Delete `App\Entity\*`
- Upgrade database `bin/console doctrine:schema:update --force --complete`
- Remove `composer remove pushword/svg` (and from bundles !)
- Drop get('assetsVersionned'), prefer use `getStylesheets` or `getJavascript`
- Change `navbar_items` format from a list with `{anchor: string, attr: ...}` to an object with `{'anchor': {href:...}}`

## To 0.1.9973

In your `assets/webpack.config.js`, upgrade the getEncore function to add at the first parameter the symfony webpack-encore dependecy (`const Encore = require('@symfony/webpack-encore')`).

Permit to avoid the compilation error.

```
TypeError: The 'compilation' argument must be an instance of Compilation
``

## To 0.1.084

* Delete src/DataFixtures folder
* Update composer.json to allow sf7 `6.*` -> `6.*|7.*`

## To 0.0.800

-   Update doctrine

```

php bin/console make:migration && php bin/console doctrine:migrations:migrate

```

-   Update main content's block

```

php vendor/pushword/admin-block-editor/src/Installer/Update795.php~

```

-   **Update front-end to tailwind 3** by keeping only `@pushword/js-helper` as dependency in your `assets/package.json` and **simplify** `webpack.config.js` and, if needed, `app.js` and `app.css` (see the simpliest way `packages/skeleton/assets`)

## To 0.0.728

-   Update stylesheets

```

cd assets && yarn && yarn encore production

```

-   Update doctrine

```

bin/console make:migration && bin/console doctrine:migrations:migrate

````

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
````
