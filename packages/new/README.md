# Your new Pushword site

This is a fresh [Pushword](https://pushword.piedweb.com) install — a modular CMS built on
Symfony, where content is Markdown + Twig and every feature is its own bundle.

Replace this README with your own once the site is yours.

## Run it

```shell
php -S 127.0.0.1:8004 -t public/
# or, with the Symfony CLI
symfony server:start -d
```

A `Caddyfile` is included if you prefer [FrankenPHP](https://frankenphp.dev), which also
runs Pushword in worker mode.

## Log in

Open `/admin` with the account the installer asked you for.

If it ran unattended — CI, a script, `composer --no-interaction` — it could not ask,
and fell back to a demo account whose credentials are published:

```
admin@example.tld
p@ssword
```

**Change it right away** — from the admin, or with
`php bin/console pw:user:create you@example.com 'your-password' ROLE_SUPER_ADMIN`.

## First steps

1. **Name your site** — `config/packages/pushword.yaml` holds hosts, locales and
   templates. `php bin/console pw:new` adds another site to it.
2. **Write a page** — from `/admin`, or as Markdown files with
   [Flat](https://pushword.piedweb.com/extension/flat).
3. **Make it yours** — override the Twig views in `templates/`, see
   [themes](https://pushword.piedweb.com/themes).
4. **Delete the demo content** — the pages and media that came with the install are
   examples, not a starting point.

## What came with it

| | |
|---|---|
| [Admin](https://pushword.piedweb.com/extension/admin) | Manage pages, media and users |
| [Advanced Main Image](https://pushword.piedweb.com/extension/advanced-main-image) | Choose each page's main image format, up to a hero |
| [API](https://pushword.piedweb.com/extension/api) | Token-authenticated REST mirror of the admin, for scripts and agents |
| [Block editor](https://pushword.piedweb.com/extension/admin-block-editor) | Write in blocks, stored as Markdown |
| [Conversation](https://pushword.piedweb.com/extension/conversation) | Contact form, comments, any user input |
| [Flat](https://pushword.piedweb.com/extension/flat) | Write pages as Markdown files, synced with the database |
| [Page Scanner](https://pushword.piedweb.com/extension/page-scanner) | Find dead links, 404s and redirects |
| [Static Generator](https://pushword.piedweb.com/extension/static-generator) | Export the site as static files |
| [Template Editor](https://pushword.piedweb.com/extension/template-editor) | Edit Twig views from the admin |
| [Version](https://pushword.piedweb.com/extension/version) | Keep every past revision of a page |

Add more as you need them — search, newsletter, quizzes:

```shell
composer req pushword/search
composer req pushword/newsletter
```

The full list is on [pushword.piedweb.com/extensions](https://pushword.piedweb.com/extensions).

## Working with an AI agent

`AGENTS.md` at the root (symlinked as `CLAUDE.md`) tells an agent how this project is laid
out and points it at `vendor/pushword/docs/CLAUDE.md`, Pushword's own reference. Fill in
its *About this site* section — that is the part no agent can infer from the code.

## Update

```shell
composer update
```

## Help

- Documentation — [pushword.piedweb.com](https://pushword.piedweb.com)
- Questions and bugs — [github.com/Pushword/Pushword/issues](https://github.com/Pushword/Pushword/issues)
- Support the project — [Liberapay](https://liberapay.com/RobinPiedWeb), or a star on
  [GitHub](https://github.com/Pushword/Pushword)

## License

Pushword is MIT licensed. See the [license](https://pushword.piedweb.com/license#license).
