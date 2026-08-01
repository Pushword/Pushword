# Pushword Newsletter

Newsletter for [Pushword](https://pushword.piedweb.com): audiences, contacts with
custom properties, segmented campaigns, criteria-driven automations and content
triggers that mail your readers when you publish — no CRM, no worker, no
third-party ESP.

```twig
{{ newsletter_form('my-audience') }}
```

```shell
* * * * * cd /path/to/app && php bin/console pw:newsletter:tick
```

Read the [documentation](https://pushword.piedweb.com/extension/newsletter).

## Install

```shell
composer require pushword/newsletter
```

Register the bundle and its routes:

```php
// config/bundles.php
Pushword\Newsletter\PushwordNewsletterBundle::class => ['all' => true],
```

```yaml
# config/routes.yaml
pushword_newsletter:
    resource: "@PushwordNewsletterBundle/NewsletterRoutes.yaml"
```

Then update the schema (`bin/console doctrine:schema:update --force`) and create
an audience — from the admin, or over `POST /api/newsletter/audience` when
`pushword/api` is installed.

## License

MIT — see [LICENSE](LICENSE).
