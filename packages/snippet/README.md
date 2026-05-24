# Pushword Snippet

Editor-owned reusable content fragments and developer-registered components for
[Pushword](https://pushword.piedweb.com), invoked from page content with a single
Twig function:

```twig
{{ snippet('footer-note') }}
{{ snippet('cta', { title: 'Ready to start?', buttonText: 'Contact us' }) }}
```

See the [documentation](https://pushword.piedweb.com/extension/snippet).

## Install

```shell
composer require pushword/snippet
php bin/console doctrine:schema:update --force
```
