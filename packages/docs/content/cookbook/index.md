---
title: Puswhord Cookbook
h1: Cookbook
twig: 0
toc: true
---

## Installation and Personalization

### Editing an entity

When you install a new project, entities are installed full featured.
To remove default feature or add new one, you just need to edit `Entity/Page.php`, `Entity/Media.php` or `Entity/User.php` in your `src` folder.
Look at [Pushword\Core\Entity\Page.php](https://github.com/Pushword/Core/blob/master/src/Entity/Page.php) & co for full traits list.

You can easily extends and override Admin as well.

### Edit the navbar

See [override current theme](/override-theme).

#### i18n link on logo

```
{% set logo = page.locale == 'en' ? {'alt' : 'Alps Guide', 'href':'/en'} : {'alt':'Accompagnateur Vercors Pied Vert'} %}
```

### Make internal link

```twig
{{ "
{{ homepage() }}
{{ page('my-slug') }}
{{ page(pageObjecct) }}" }}
```

### Optimize CSS

Activate purge css commented code in `webpack.config.js`

## Maintaining

### Update all cached image

Command line

```
php bin/console pushword:media:cache
```

## Editor Tips

See help in the admin (`/admin/markdown-cheatsheet`).
