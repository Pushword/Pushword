---
title: Upgrade a Pushword installation | Changelog
h1: Upgrade Guide
toc: true
parent: installation
---

Smooth way is to use [composer](https://getcomposer.org), a dependency manager for PHP.

Run `composer update` and the job is done (almost).

If you are doing a major upgrade, find the upgrade guide down there.

## To 1.0.0-rc80

- Default static website are now exported under `static/%main_host%` directory instead of `%main_host%`.
- remove `admin_block_editor_type_to_prose` config from [AdminBlockEditor] (not used)
- in your `config/routes.yaml`, replace `resource: "@PushwordCoreBundle/Resources/config/routes/all.yaml"` by `resource: "@PushwordCoreBundle/Resources/config/routes.yaml"`

### To tailwind v4 and puswhord/assets

- heropattern plugin has been dropped, import manually the properties used in your css (see https://heropatterns.com)
- multicolumn plugin has been dropped
- update your template files to tailwind v4 classes (AI recommended `Scan all files inside the ./templates directory.
Identify and update every Tailwind CSS class name from version 3 syntax to version 4 syntax, ensuring full compatibility with Tailwind 4’s new naming conventions and features.
https://tailwindcss.com/docs/upgrade-guide#changes-from-v3`)
- update your project configuration (see example in `packages/docs/assets/`)
- transform your `./assets/webpack.config.js` to `./vite.config.js` (see `vendor/pushword/skeleton/vite.config.js`), same for `./assets/package.json` to `./package.json` and install `composer require pentatrion/vite-bundle`

### To editorjs backed in markdown

1. Make a database backup
2. Run the command to see what will be converted

```
php bin/console pushword:json:to-markdown --dry-run
```

3. Run the command to convert the data

```
php bin/console pushword:json:to-markdown --force
# by host
php bin/console pushword:json:to-markdown --host=example.com
# or by page id
php bin/console pushword:json:to-markdown --page-id=123
```

4. Check the result

## To 1.0.0-rc79

- Delete `App\Entity\*`
- Upgrade database `bin/console doctrine:schema:update --force`
- Remove `composer remove pushword/svg` (and from `config/bundles.php`)
- Drop get('assetsVersionned'), prefer use `getStylesheets` or `getJavascript`
- Change twig function `page_position` for `breadcrumb_list_position`

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
