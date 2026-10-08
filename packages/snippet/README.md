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

Pushword is a modular CMS — one [Symfony](https://symfony.com) bundle for the core and one bundle per feature. See the full list and guides at [pushword.piedweb.com/extensions](https://pushword.piedweb.com/extensions).

## Contributing

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Pushword/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
