# Pushword Snippet

Editor-owned **reusable content fragments** and developer-registered **components** for [Pushword](https://pushword.piedweb.com), invoked from page content with a single Twig function.

## Features

- **Content snippets**: reusable Markdown owned by the editor (CTA, author box, footer note), one row per host/locale.
- **Component snippets**: dev-registered components with a parameter schema and a Twig template.
- One call for both: `{{ snippet('name', {params}) }}`, with host/global fallback resolution.
- Editable from the admin, as **flat files**, and **translatable per host**.

## Installation

```shell
composer require pushword/snippet
php bin/console doctrine:schema:update --force
```

## Usage

```twig
{{ snippet('footer-note') }}
{{ snippet('cta', { title: 'Ready to start?', buttonText: 'Contact us' }) }}
```

## Documentation

See [pushword.piedweb.com/extension/snippet](https://pushword.piedweb.com/extension/snippet).

## The Pushword ecosystem

Pushword is a modular CMS — one [Symfony](https://symfony.com) bundle for the core and one bundle per feature. Pick only what you need:

**Core**
- [pushword/core](https://github.com/Pushword/core) — Symfony-based CMS core: Page, Media & User entities, Markdown + Twig rendering.

**Editing & admin**
- [pushword/admin](https://github.com/Pushword/admin) — EasyAdmin interface to manage pages, media and users.
- [pushword/admin-block-editor](https://github.com/Pushword/admin-block-editor) — Gutenberg-like block editor (stores Markdown).
- [pushword/advanced-main-image](https://github.com/Pushword/advanced-main-image) — Hero images & main-image format control.
- [pushword/template-editor](https://github.com/Pushword/template-editor) — Edit Twig templates online.
- **pushword/snippet** — Reusable content fragments & components. *(this package)*

**Content & workflow**
- [pushword/flat](https://github.com/Pushword/flat) — Flat-file (Markdown + Git) CMS mode.
- [pushword/version](https://github.com/Pushword/version) — Page & snippet versioning.
- [pushword/page-update-notifier](https://github.com/Pushword/page-update-notifier) — Email alerts on content changes.
- [pushword/conversation](https://github.com/Pushword/conversation) — Comments, contact & newsletter forms.

**Publishing & performance**
- [pushword/static-generator](https://github.com/Pushword/static-generator) — Export a static website (GitHub Pages, Apache, FrankenPHP).
- [pushword/search](https://github.com/Pushword/search) — SQLite full-text search (Loupe), zero infra.
- [pushword/page-scanner](https://github.com/Pushword/page-scanner) — Find dead links, 404s, redirects & TODOs.
- [pushword/api](https://github.com/Pushword/api) — Token-authenticated REST API.

**Tooling**
- [pushword/installer](https://github.com/Pushword/installer) — Project & package installer.
- [pushword/js-helper](https://github.com/Pushword/js-helper) — Front-end JavaScript helpers.

Full list and guides on [pushword.piedweb.com/extensions](https://pushword.piedweb.com/extensions).

## Contributing

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Core/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license#license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
