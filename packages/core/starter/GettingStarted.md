Everything you need for the first hour. The full documentation lives on
[pushword.piedweb.com](https://pushword.piedweb.com).

## Write a page

From [the admin](/admin): **Pages → New**. A page needs a **slug** (its URL), an **h1**
and some content. The rest — title, meta description, main image — is optional and falls
back to sensible values.

Content is **Markdown**. The editor previews it side by side, so you can see what a
paragraph, a list or a table will look like as you type. [What you can
write](/examples) demonstrates every tool available.

## Name your site

`config/packages/pushword.yaml` holds your hosts, locales and templates. It ships almost
empty on purpose — uncomment what you need:

```yaml
pushword:
    name: My Website
    host: example.com
    locale: en
```

To run several sites from one install, add them under `apps:`, or let the command do it:

```bash
php bin/console pw:new
```

## Change the look

Twig views live in `templates/`. Copy the one you want to change out of
`vendor/pushword/core/src/Resources/views/` and edit your copy — never the file in
`vendor/`, which the next update overwrites. See
[themes](https://pushword.piedweb.com/themes).

Colours, fonts and spacing are Tailwind classes; `assets/` holds the CSS and JS, built
with Vite.

## Useful commands

```bash
php bin/console list pw            # every Pushword command
php bin/console cache:clear        # after changing config or templates
php bin/console pw:user:create     # add someone to the admin
php bin/console pw:page:delete --tag=demo   # remove the demo pages
```

The database is SQLite, at `var/app.db`. There are no migrations — after changing an
entity, run `php bin/console doctrine:schema:update --force`.

## Add features

Pushword is one bundle per feature. Install only what you need:

```bash
composer req pushword/version   # page history and diffs
composer req pushword/search    # full-text search, no extra infrastructure
composer req pushword/flat      # keep content as Markdown files in Git
```

Each one registers its own routes and configuration on install. The full list is on
[pushword.piedweb.com/extensions](https://pushword.piedweb.com/extensions).
