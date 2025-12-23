---
title: 'Find your custom theme for a Pushword CMS'
h1: 'Entity Filter'
id: 23
publishedAt: '2025-12-21 21:55'
parentPage: homepage
name: Theme
filter_twig: 0
---

The **Entity Filter** may be the most powerful thing built for Pushword.

It's offer to apply _what you want_ on properties from a class (mostly entity and often mostly `Page`).

It can be called from **PHP** (from `Pushword\Core\Component\EntityFilter\Manager`) or directly in **Twig** template file (with `pw` function).

It's permit, for example, to render markdown in title : `{{ pw(page).title|raw }}` for title being `Page **title**` will render `Page <strong>Title</strong>` if markdown filter is configured for `string` or for `title` in `pushword.filters` or directly in the `app.filters` (see [configuration](/configuration)).

Each `entity` (mostly `Page`) can override one or multiple just setting `filter_*filterName*: false` in `customProperties`. See for #[this page](https://github.com/Pushword/Pushword/tree/main/packages/docs/content/component/entity-filter.md), twig is disabled.

You can find the list of avalaible filters in #[core/Component/EntityFilter/Filter](https://github.com/Pushword/Pushword/tree/main/packages/core/src/Component/EntityFilter).

You can easily create a new filter implementing `Pushword\Core\Component\EntityFilter\Filter\FilterInterface`.

For now, filter are partially autowired with current `App`, current `Manager`, `Twig` and the current `entity`.

See `Pushword\Core\Component\EntityFilter\Manager::getFilterClass` and `...\Filter\Required***Trait.php`.

You can easily create an extension wich add special action after filtering based on `entity_filter.after_filtering` event wich contain `Manager` and `string property`.