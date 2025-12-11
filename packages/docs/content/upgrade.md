---
title: Upgrade a Pushword installation | Changelog
h1: Upgrade Guide
toc: true
parent: installation
---

Smooth way is to use [composer](https://getcomposer.org), a dependency manager for PHP.

Run `composer update` and the job is done (almost).

If you are doing a major upgrade, find the upgrade guide down there.

## To 1.0.0-rc247

1. If you customized your app.js, add if you use startShowMore component :

```js
import { initShowMore } from './ShowMore.js'
initShowMore()
```

2. Run `php bin/console pw:image:cache` to generate the new avif images.

## To 1.0.0-rc80

- [ ] Default static website are now exported under `static/%main_host%` directory instead of `%main_host%`.
- [ ] in your `config/routes.yaml`, replace `resource: "@PushwordCoreBundle/Resources/config/routes/all.yaml"` by `resource: "@PushwordCoreBundle/Resources/config/routes.yaml"`

```bash
sed -i "s|@PushwordCoreBundle/Resources/config/routes/all.yaml|@PushwordCoreBundle/Resources/config/routes.yaml|g" ./config/routes.yaml

```

- [ ] Check your `config/packages` and compare it with the new one in [`vendor/pushword/skeleton/config/packages`](https://github.com/Pushword/Pushword/blob/main/packages/skeleton/config/packages) - flex add tons of config but you need to maintain them. Best practice is to remove theme and to keep `framework.yaml` (you can easily compare with the maintained one in the skeleton), `pentatrion.yaml`, `twig.yaml`, `web_profiler.yaml`, `pushword.yaml` .
- [ ] check if flex install you a `templates/base.html.twig` file, if yes, remove it.

- [ ] remove sidecar yaml or json files in media `rm media/*.{yaml,json}` (we are now using a global index.csv)

### Media entity change

- media.media ➜ media.fileName
- media.name ➜ media.alt

### From Sonata to EasyAdmin

- [ ] Remove from you `config/bundles.php` the bundles related to Sonata :

```php
    Knp\Bundle\MenuBundle\KnpMenuBundle::class => ['all' => true],
    Sonata\Form\Bridge\Symfony\SonataFormBundle::class => ['all' => true],
    Sonata\Twig\Bridge\Symfony\SonataTwigBundle::class => ['all' => true],
    Sonata\BlockBundle\SonataBlockBundle::class => ['all' => true],
    Sonata\AdminBundle\SonataAdminBundle::class => ['all' => true],
    Sonata\DoctrineORMAdminBundle\SonataDoctrineORMAdminBundle::class => ['all' => true],
```

- [ ] Delete respective configuration to Sonata Bundles in your `config/packages/` directory (searched for `sonata`)

### Default static analysis, rector, php-cs-fixer

The default installation is now creating. Look in [`vendor/pushword/core/install.php`](https://github.com/Pushword/Pushword/blob/main/packages/core/install.php) how to implement it in your project.

### To tailwind v4 and puswhord/js-helper upgrades

- [ ] update your template files to tailwind v4 classes (AI recommended, prompt below)

```
Scan all files inside the ./templates directory.
Identify and update every Tailwind CSS class name from version 3 syntax to version 4 syntax, ensuring full compatibility with Tailwind 4’s new naming conventions and features.
https://tailwindcss.com/docs/upgrade-guide#changes-from-v3`)
```

- [ ] transform your `./assets/webpack.config.js` to `./vite.config.js`
      See [`vendor/pushword/skeleton/vite.config.js`](https://github.com/Pushword/Pushword/blob/main/packages/skeleton/vite.config.js)
- [ ] same for `./assets/package.json` to `./package.json`
- [ ] install `composer require pentatrion/vite-bundle`
- [ ] some utility has been moved from the webpack config helper function to the app.css file, update your app.css to use the new utilities
- [ ] update your project configuration `config/packages/pushword.yaml`

```yaml
# FROM
...
        assets:
          {
            javascripts: ["/assets/app.js?6"],
            stylesheets: ["/assets/style.css?65"],
          },

# TO (adapt with your vite.config.js output)
        assets:
          {
            vite_javascripts: ["app"],
            vite_stylesheets: ["theme"],
          },

```

- [ ] if you have a custom `app.css`, upgrade it to tailwind v4 (see [`vendor/pushword/js-helper/src/app.css`](https://github.com/Pushword/Pushword/blob/main/packages/js-helper/src/app.css)) - be careful with the `@source` paths.
- [ ] Check your pentatrion config in `config/packages/`, must be like this :

```yaml
pentatrion_vite:
build_directory: assets
```

Notes :

- heropattern plugin has been dropped, import manually the properties used in your css (see https://heropatterns.com)
- multicolumn plugin has been dropped

### To editorjs backed in markdown

1. Make a database backup
2. Run the command to see what will be converted

```
php bin/console pw:json-to-markdown --dry-run
```

3. Run the command to convert the data

```
php bin/console pw:json-to-markdown --force
# by host
php bin/console pw:json-to-markdown --host=example.com
# or by page id
php bin/console pw:json-to-markdown --page-id=123
```

4. Check the result

## To 1.0.0-rc79

- Delete `App\Entity\*`
- Upgrade database `bin/console doctrine:schema:update --force`
- Remove `composer remove pushword/svg` (and from `config/bundles.php`)
- Drop get('assetsVersionned'), prefer use `getStylesheets` or `getJavascript`
- Change twig function `page_position` for `breadcrumb_list_position`
