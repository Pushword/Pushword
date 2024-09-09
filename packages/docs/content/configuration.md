---
title: Configure a fresh install of Pushword
h1: Configuration
toc: true
parent: installation
---

Before to **code**, Pushword offers the ability to configure a lot of things from a **yaml** (or PHP if you preferred) configuration file.

If you used the [automatic installer](/installation) to create a new _pushword project_, just open `config/packages/pushword.yaml` and start configure your **website**.

<span class"bg bg-primary text-white p-3">Good to remember</span> In the configuration a `website` has a more generic name, it's an `app`

When you create a new _pushword project_ important property to configure now is **host** (eg: `host: localhost.dev`). Then you are ready to use your website.

## Two levels : `Global` vs `App` {id=configuration-types}

Before to digg in your configuration file, you need to know there is two different config properties : the `global` ones and the `app` ones.

This specificity is the same for almost all officially maintained pushword extensions.

If you want the same configure value for all `app`, you can configure globally and the value will be transmit to each app config.

### How to difference global from app configuration without reading the docs ?

At the begining of each configuration's file (if you do [a fresh dump](#dump)), you will find a property named `app_fallback_properties`.

This one define the **app** configuration.

### How to get all configuration property without reading the docs ? {id=dump}

```shell
php bin/console config:dump-reference PushwordCoreBundle
```

<span class"bg bg-primary text-white p-3">Good to remember</span> for each _Pushword Projet_, you have a lot of different extension, you may find the good configuration property in the concerned extension.

For example, to get the admin configuration option

```shell
php bin/console config:dump-reference PushwordAdminBundle
# or php bin/console config:dump-reference pushword_admin
```

## Can I add an custom app configuration property that I will be able to use in my custom code ?

Yes and no !

No because tanks to the **Symfony Configuration Component**, the configuration file is verified before to be used.

And yes, because we want to create quickly a prototype or a POC, so Pushword add a special `custom_properties` under wich you can set the property you want.

## Configuration Settings

So remember, we are in the `config/packages/pushword.yaml` and this is a dump for the file with all properties.

If you don't understand what the purpose of a property property. #[Feel free to ask](https://github.com/Pushword/Pushword/issues/new), I will list answers on this page.

```yaml
pushword:
  app_fallback_properties:
    - hosts
    - locale
    - locales
    - name
    - base_url
    - template
    - template_dir
    - entity_can_override_filters
    - filters
    - assets
    - custom_properties
  public_dir: '%kernel.project_dir%/public'
  # Dir where files will be uploaded when using admin.
  media_dir: '%kernel.project_dir%/media'
  # Used to generate browser path. Must be accessible from public_dir.
  public_media_dir: media
  database_url: 'sqlite:///%kernel.project_dir%/var/app.db'
  locale: '%kernel.default_locale%'
  # eg: fr|en
  locales: '%kernel.default_locale%'
  name: Pushword
  host: localhost
  hosts:
    # Default:
    - %pw.host%
  base_url: 'https://%pw.host%'
  assets:
    # Defaults:
    stylesheets:
      - bundles/pushwordcore/tailwind.css
    javascripts:
      - bundles/pushwordcore/page.js
  filters:
    # Defaults:
    main_content: twig,date,email,obfuscateLink,htmlObfuscateLink,image,phoneNumber,punctuation,markdown,mainContentSplitter,extended
    name: twig,date,name,extended
    title: twig,date,elseH1,extended
    string: twig,date,email,obfuscateLink,phoneNumber,extended
  entity_can_override_filters: true
  image_filter_sets:
    default:
      quality: 90
      filters:
        downscale:
          - 1980
          - 1280
    height_300:
      quality: 82
      filters:
        heighten:
          0: 300
          constraint: $constraint->upsize();
    thumb:
      quality: 80
      filters:
        fit:
          - 330
          - 330
    xs:
      quality: 85
      filters:
        widen:
          0: 576
          constraint: $constraint->upsize();
    sm:
      quality: 85
      filters:
        widen:
          0: 768
          constraint: $constraint->upsize();
    md:
      quality: 85
      filters:
        widen:
          0: 992
          constraint: $constraint->upsize();
    lg:
      quality: 85
      filters:
        widen:
          0: 1200
          constraint: $constraint->upsize();
    xl:
      quality: 85
      filters:
        widen:
          0: 1600
          constraint: $constraint->upsize();
  template: '@Pushword'
  template_dir: '%kernel.project_dir%/templates'
  custom_properties: []
  apps:
    # Default:
    -
```

## `pushword.apps`

It's under this property you may customize _per app_ property.

Example :

```yaml
pushword:
  # ...
  apps:
    - { hosts: ['localhost.dev', 'localhost'], base_url: 'https://localhost.dev', randomTest: 123, admin_block_editor: false, admin_block_editor_disable_listener: true }
    - {
        hosts: [pushword.piedweb.com],
        base_url: 'https://pushword.piedweb.com',
        flat_content_dir: '%kernel.project_dir%/../docs/content',
        static_dir: '%kernel.project_dir%/../../docs',
        name: 'Pushword',
        static_generators:
          [
            Pushword\StaticGenerator\Generator\PagesGenerator,
            Pushword\StaticGenerator\Generator\RobotsGenerator,
            Pushword\StaticGenerator\Generator\ErrorPageGenerator,
            Pushword\StaticGenerator\Generator\CopierGenerator,
            Pushword\StaticGenerator\Generator\CNAMEGenerator,
          ],
        assets: { javascripts: ['assets/app.js'], stylesheets: ['assets/tw.css'] },
        color_bg: '#fff',
        static_copy: ['assets'],
      }
    - { hosts: [admin-block-editor.test], base_url: 'https://admin-block-editor.test' }
```

In this example, I defined 3 diffrents websites (managed by the same Puswhord Installation) where I customized a few properties
for each website.

You don't recognize all the properties ?

That's normal, a few came from [extensions](/extensions).

## Customize favicon, stylesheets and javascript with `pushword.assets` or `pushword.apps[...].assets` {id=assets}

Example :

```yaml
pushword:
    # ...
    assets: {
        favicons: 'assets/favicons/favicons.ico',
        javascripts:
            - 'assets/app.js'
            - { src: 'https://.../example.js', 'defer'},
        stylesheets: ['assets/tw.css']
    },
```

This configuration variables are used in the default [base.html.twig](https://github.com/Pushword/Pushword/blob/main/packages/core/src/templates/base.html.twig#).

This one could be directly overrided, see [overriding twig template](override-theme.md).

### `pushword.apps[...].favicons_path`

A deprecated way to update favicons is to use favicons_path at the app level configuration. It will use this folder path in [component/favicon.html.twig](https://github.com/Pushword/Pushword/blob/main/packages/core/src/templates/component/favicon.html.twig)
