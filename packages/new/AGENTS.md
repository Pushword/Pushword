# This site

A [Pushword](https://pushword.piedweb.com) site: a modular CMS made of Symfony bundles.
Content is Markdown + Twig, the database is SQLite, and each feature is its own bundle
under `vendor/pushword/`.

Replace this file's **About this site** section with what is specific to you — the rest is
a starting point, edit it freely.

## Read first

Pushword ships its own reference for AI agents. Read it before changing anything:

- `vendor/pushword/docs/CLAUDE.md` — conventions, content model, commands
- `vendor/pushword/docs/content/` — one `.md` per topic; `architecture.md` and
  `extensions.md` are the two that orient you fastest
- `vendor/pushword/core/src/Entity/Page.php` — the main entity; its docblock lists the
  traits, fields and relations
- `vendor/pushword/core/src/Entity/Media.php` — the media entity

Do not duplicate Pushword knowledge here. Link to those files instead, so this stays true
when you upgrade.

## Commands

Run from the project root:

```bash
php bin/console list pw          # every Pushword command
php bin/console cache:clear      # after each change
php bin/console pw:user:create   # add a user (email password ROLE_…)
php bin/console pw:new           # register another site in config/packages/pushword.yaml
```

Many `pw:*` commands detect when an agent runs them and emit one compact JSON line
instead of progress bars — force it with `--format=agent`, or `--format=text` for the
human version. `vendor/pushword/docs/content/agent-output.md` lists which ones.

## Where things live

| | |
|---|---|
| `config/packages/pushword.yaml` | sites, hosts, locales, templates |
| `templates/` | your Twig overrides — see `vendor/pushword/docs/content/override-theme.md` |
| `assets/` | CSS/JS, built with Vite |
| `media/` | uploaded files |
| `var/app.db` | the SQLite database |
| `src/` | your own entities, controllers and services |

## Working rules

- **Nothing speculative**: no unrequested features, abstractions, or error handling for
  cases that cannot happen.
- Change the template, not the bundle. Overriding a Twig view beats patching
  `vendor/`, which upgrades will overwrite.
- The database has no migrations: `php bin/console doctrine:schema:update --force`.
- Clear the cache after each change, and check the page in a browser — a wrong Twig block
  name renders nothing and still returns 200.

## About this site

Describe here what an agent cannot read from the code:

- **Purpose** — what this site is for, and who it is for.
- **Hosts and locales** — one row per host, with its locale and what it covers.
- **Deployment** — how it goes live, and anything that must run afterwards.
- **Editorial rules** — tone, structure, naming, what never to publish.
- **Invariants** — anything the framework does not enforce but you rely on.
