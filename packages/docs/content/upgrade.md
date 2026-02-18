---
title: 'Upgrade a Pushword installation | Changelog'
h1: 'Upgrade Guide'
publishedAt: '2026-01-30 13:24'
parentPage: installation
toc: true
---

Smooth way is to use [composer](https://getcomposer.org), a dependency manager for PHP.

Run `composer update` and the job is done (almost).

If you are doing a major upgrade, find the upgrade guide down there.

## To 1.0.0-rc373

### Media CSV moved from `media/index.csv` to `content/media.csv`

The media metadata CSV file has moved from the Flysystem-managed `media/` directory to the local `content/` directory. This makes it accessible regardless of your storage backend (local, SFTP, S3, etc.).

**Migration:**

```bash
# Move the file to its new location
mv media/index.csv content/media.csv
```

If you don't have a `media/index.csv` file, no action is needed — the next `pw:flat:sync --mode=export` will generate `content/media.csv` automatically.

## To 1.0.0-rc372

### Migration Steps

1. Run `composer update`
2. Run `php bin/console doctrine:schema:update --force` (adds new `template` column to `page` table)
3. Run `php bin/console pw:migrate` (migrates data from JSON `customProperties` to new columns, fixes typos)
4. Clear cache: `php bin/console cache:clear`
5. Update custom code per the breaking changes below

### Entity Changes

#### Page

- **Template column promoted**: `template` is now a real database column (`nullable string`), no longer stored in `customProperties` JSON. Getter/setter unchanged: `getTemplate()` / `setTemplate()`.
- **searchExcerpt typo fixed**: `getSearchExcrept()` removed. Use `getSearchExcerpt()`. In Twig: `page.searchExcrept` becomes `page.searchExcerpt`.
- **Traits inlined**: `PageEditorTrait`, `PageExtendedTrait`, `PageMainImageTrait`, `PageOpenGraphTrait`, `PageSearchTrait`, `PageRedirectionTrait` are removed. Their properties and methods are inlined directly into `Page`.
- **PHP 8.4 property hooks**: Simple properties (`$h1`, `$slug`, `$title`, `$metaRobots`, `$name`, `$editMessage`) now use property hooks. Getter/setter methods are retained for caller compatibility.
- **Redirection API changed**: `getRedirection()` now returns `?PageRedirection` value object (or `null`). Use `hasRedirection()`, `getRedirectionUrl()`, `getRedirectionCode()` instead of accessing the old string-based redirection.
- **OG/Twitter getters explicit**: `getOgTitle()`, `setOgTitle()`, `getOgDescription()`, etc. are explicit methods (no longer resolved via `__call`). They still store data in `customProperties` JSON.
- **`__call` minimal**: Only proxies `getCustomProperty()` for Twig ergonomics (`page.someKey`). No method-existence cache, no complex resolution.

#### Media

- **Image data as embeddable**: `ImageTrait` replaced by `ImageData` Doctrine embeddable (`#[ORM\Embedded(class: ImageData::class, columnPrefix: false)]`). DQL queries must use `m.imageData.width`, `m.imageData.height`, `m.imageData.ratioLabel` etc.
- **Removed deprecated methods**: `getMedia()` and `getName()` removed. Use `getFileName()` and `getAlt()`.
- **`$disableRemoveFile` removed**: The public flag on Media is gone.

#### User

- `CustomPropertiesTrait` replaced by `ExtensiblePropertiesTrait` (same JSON column, cleaner API).
- `getSalt()` removed (not needed with bcrypt).

#### SharedTrait: ExtensiblePropertiesTrait

Replaces `CustomPropertiesTrait`:

| Old API                                       | New API                                                             |
| --------------------------------------------- | ------------------------------------------------------------------- |
| `$standAloneCustomProperties`                 | `getUnmanagedPropertiesAsYaml()` / `setUnmanagedPropertiesAsYaml()` |
| `$registeredCustomPropertyFields`             | `registerManagedPropertyKey()` / `getManagedPropertyKeys()`         |
| Method-existence cache (`$methodExistsCache`) | Removed                                                             |
| `CustomPropertiesException`                   | `InvalidArgumentException`                                          |

Core API unchanged: `getCustomProperty()`, `setCustomProperty()`, `hasCustomProperty()`, `removeCustomProperty()`, `getCustomPropertyScalar()`, `getCustomPropertyList()`.

### Architecture Changes

#### Site Configuration

| Old                                     | New                                                                     |
| --------------------------------------- | ----------------------------------------------------------------------- |
| `Pushword\Core\Component\App\AppConfig` | `Pushword\Core\Site\SiteConfig`                                         |
| `Pushword\Core\Component\App\AppPool`   | `Pushword\Core\Site\SiteRegistry` + `Pushword\Core\Site\RequestContext` |

- `SiteRegistry`: Pure registry for site configurations. Methods: `get()`, `getDefault()`, `findByHost()`, `getHosts()`, `getAll()`, `isKnownHost()`.
- `RequestContext`: Request-scoped state. Methods: `switchSite()`, `setCurrentPage()`, `getCurrentPage()`, `requirePage()`, `getCurrentSite()`, `getLocale()`.
- `SiteRegistry` also delegates to `RequestContext` for convenience: `switchSite()`, `setCurrentPage()`, `getCurrentPage()`, `getMainHost()`, `getLocale()`, etc.

#### Template Resolution

| Old                                                | New                                               |
| -------------------------------------------------- | ------------------------------------------------- |
| `AppConfig::getView()` (with Twig+Cache on config) | `TemplateResolver::resolve()` (dedicated service) |

- `SiteConfig::getView()` still works (delegates to `TemplateResolver`).
- `TemplateResolver` is a standalone service (`Pushword\Core\Template\TemplateResolver`) injected via DI.

#### Page Resolution

| Old                                                              | New                    |
| ---------------------------------------------------------------- | ---------------------- |
| Duplicated `findPage()` in `PageController` and `FeedController` | `PageResolver` service |

- `PageResolver::findPageOr404()`: Shared page lookup logic (slug normalization, pager extraction, permission checks).
- `PageResolver::normalizeSlug()`: Static method for slug normalization.

#### Content Pipeline

| Old                                                | New                                            |
| -------------------------------------------------- | ---------------------------------------------- |
| `Pushword\Core\Component\EntityFilter\Manager`     | `Pushword\Core\Content\ContentPipeline`        |
| `Pushword\Core\Component\EntityFilter\ManagerPool` | `Pushword\Core\Content\ContentPipelineFactory` |

- `ContentPipeline` adds explicit typed getters: `getMainContent()`, `getTitle()`, `getName()`.
- `ContentPipelineFactory::get(Page)` creates pipelines (replaces `ManagerPool::getManager()`).
- The `pw()` Twig function is now on `ContentPipelineFactory` (moved from `ManagerPool`).
- **Legacy Manager/ManagerPool still exist** for backward compatibility with existing filters. Filters still receive `Manager` in their `apply()` signature.

#### Events

- `PushwordEvents` catalog class created at `Pushword\Core\Event\PushwordEvents` with centralized constants: `FILTER_BEFORE`, `FILTER_AFTER`, `ADMIN_MENU`, `ADMIN_LOAD_FIELD`.
- Existing event classes (`FilterEvent`, `AdminMenuItemsEvent`, `FormField\Event`) now reference `PushwordEvents` constants.

### Admin Changes

- `standAloneCustomProperties` form field binding changed to `unmanagedPropertiesAsYaml`.
- `searchExcrept` form field binding changed to `searchExcerpt`.

### Twig Template Changes

| Old                  | New                  |
| -------------------- | -------------------- |
| `page.searchExcrept` | `page.searchExcerpt` |

Other Twig access patterns unchanged: `page.ogTitle`, `page.h1`, `page.slug`, `page.someCustomKey` all work as before.

## To 1.0.0-rc371

### Media Entity Methods Moved to Utility Class (Breaking)

The following protected methods have been removed from the `Media` entity and moved to a new `MediaFileName` utility class:

- `extractExtension()`
- `slugifyPreservingExtension()`

**If you have a custom Media subclass** that calls or overrides these methods:

**Before:**

```php
$extension = $this->extractExtension($filename);
$slugified = $this->slugifyPreservingExtension($filename, $extension);
```

**After:**

```php
use Pushword\Core\Utils\MediaFileName;

$extension = MediaFileName::extractExtension($filename);
$slugified = MediaFileName::slugifyPreservingExtension($filename, $extension);
```

### Dimensions Value Object (Breaking)

`Media::getDimensions()` and the `image_dimensions()` Twig function now return a `Dimensions` object instead of an array.

**PHP Code Changes:**

```php
// Before
$dimensions = $media->getDimensions();
$width = $dimensions[0];
$height = $dimensions[1];

// After
$dimensions = $media->getDimensions();
$width = $dimensions->width;
$height = $dimensions->height;
// Or use toArray() for backward compatibility:
$arr = $dimensions->toArray(); // [width, height]
```

**Twig Template Changes:**

```twig
{# Before #}
{% set width = image_dimensions(image)[0] %}
{% set height = image_dimensions(image)[1] %}

{# After #}
{% set width = image_dimensions(image).width %}
{% set height = image_dimensions(image).height %}
```

### Installer Package Changes

The `pushword/installer` package is no longer removed after initial project setup. It now stays as a dependency to support automatic setup when adding new Pushword packages via `composer require`.

**If you have orphaned scripts in your `composer.json`** referencing `Pushword\Installer` classes that cause errors:

1. Either re-add `pushword/installer` to your dependencies:

   ```bash
   composer require pushword/installer
   ```

2. Or remove the orphaned scripts from `composer.json`:
   ```json
   "scripts": {
       "post-install-cmd": ["@auto-scripts"],
       "post-update-cmd": ["@auto-scripts"]
   }
   ```
   And remove `post-autoload-dump` if it only contains `Pushword\Installer\PostAutoloadDump::runPostAutoload`.

### Conversation Route Change (Breaking)

The conversation form route has changed from path-based to query-based parameters for `host` and `locale`.

**Before:** `/conversation/{type}/{referring}/{host}/{locale}`

**After:** `/conversation/{type}/{referring}?host=...&locale=...`

If you have custom templates or JavaScript that generates conversation URLs manually, update them to use query parameters instead of path segments.

## To 1.0.0-rc341

### Unified Notification Email Service

The email notification system has been unified with a new `NotificationEmailSender` service. If you extended or customized email sending in your code:

**Configuration Changes:**

Two new global config keys have been added that serve as defaults for all notification services:

```yaml
pushword:
  notification_email_from: 'noreply@example.com'
  notification_email_to: 'admin@example.com'
```

These global defaults are used when package-specific keys (`conversation_notification_email_from`, `page_update_notification_from`, etc.) are not set.

**Code Changes (only if you extended these services):**

- `MagicLinkMailer` now uses `NotificationEmailSender` instead of `MailerInterface`
- `NewMessageMailNotifier` now uses `NotificationEmailSender` instead of `MailerInterface`
- `PageUpdateNotifier` now uses `NotificationEmailSender` instead of `MailerInterface` + `Twig`

## To 1.0.0-rc333

See [Template Change](/upgrade-template-change)

## To 1.0.0-rc294

### MainContentSplitter replaced by Twig function

The `MainContentSplitter` filter has been removed from the default configuration. The `pw(page).mainContent` now returns a **string** (processed HTML) instead of a `SplitContent` object.

**If you use custom templates** that access `pw(page).mainContent.chapeau`, `pw(page).mainContent.body`, etc., you need to update them:

**Before:**

```twig
{{ pw(page).mainContent.chapeau|raw }}
{{ pw(page).mainContent.body|raw }}
{{ pw(page).mainContent.toc|raw }}
{% for part in pw(page).mainContent.contentParts %}
    {{ part|raw }}
{% endfor %}
```

**After:**

```twig
{% set mainContent = mainContentSplit(page) %}
{{ mainContent.chapeau|raw }}
{{ mainContent.body|raw }}
{{ mainContent.toc|raw }}
{% for part in mainContent.contentParts %}
    {{ part|raw }}
{% endfor %}
```

**For simple templates** that just need the full content without splitting:

```twig
{{ pw(page).mainContent|raw }}
```

The new `mainContentSplit(page)` function caches results by page ID, so you can call it multiple times without performance penalty.

## To 1.0.0-rc247

0. Upgrade to Symfony 8

```
sed -i "s|#resource: '@FrameworkBundle/Resources/config/routing/errors.xml'|resource: '@FrameworkBundle/Resources/config/routing/errors.php'|g" ./config/routes/dev/framework.yaml \
; sed -i "s|resource: '@WebProfilerBundle/Resources/config/routing/wdt.xml'|resource: '@WebProfilerBundle/Resources/config/routing/wdt.php'|g" ./config/routes/dev/web_profiler.yaml \
; sed -i "s|resource: '@WebProfilerBundle/Resources/config/routing/profiler.xml'|resource: '@WebProfilerBundle/Resources/config/routing/profiler.php'|g" ./config/routes/dev/web_profiler.yaml
```

0. If you customized your app.js, add if you use startShowMore component :

```js
import { initShowMore } from '@pushword/js-helper/src/ShowMore.js'
initShowMore()
```

2. Run `php bin/console pw:image:cache` to regenerate cached images.

## To 1.0.0-rc80

- [ ] Default static website are now exported under `static/%main_host%` directory instead of `%main_host%`.
- [ ] in your `config/routes.yaml`, replace `resource: "@PushwordCoreBundle/Resources/config/routes/all.yaml"` by `resource: "@PushwordCoreBundle/Resources/config/routes.yaml"`

```bash
sed -i "s|@PushwordCoreBundle/Resources/config/routes/all.yaml|@PushwordCoreBundle/Resources/config/routes.yaml|g" ./config/routes.yaml

```

- [ ] Check your `config/packages` and compare it with the new one in [`vendor/pushword/skeleton/config/packages`](https://github.com/Pushword/Pushword/blob/main/packages/skeleton/config/packages) - flex add tons of config but you need to maintain them. Best practice is to remove theme and to keep `framework.yaml` (you can easily compare with the maintained one in the skeleton), `pentatrion.yaml`, `twig.yaml`, `web_profiler.yaml`, `pushword.yaml` .
- [ ] check if flex install you a `templates/base.html.twig` file, if yes, remove it.

- [ ] remove sidecar yaml or json files in media `rm media/*.{yaml,json}` (we are now using a global `content/media.csv`)

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
php bin/console pw:json-to-markdown
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