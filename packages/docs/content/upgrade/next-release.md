---
title: 'Docker is offered at install time'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

<!--
The upgrade note for the next release. `.scripts/release` renames this file to
`upgrade/rc<N>.md`, adds its row to the table in `upgrade.md` and empties it back
to this scaffold, at the tag.

Write here, in the same commit as the change, whenever a release asks something of
a site that upgrades: a command to run, a config key to set, a template to copy, a
behaviour that changed under an unchanged call. A change `composer update` fully
absorbs needs no note.

- `title:` — the "What changed" cell of the index table. One line, lower case,
  written from the site's side ("the newsletter form is fetched, and CSRF-protected")
  rather than the diff's ("refactor NewsletterFormController"). Required as soon as
  the note has a section; the release stops if it is still empty.
- `run:` — the command(s) the release expects, without `php bin/console`. Omit the
  key when there is none. A list runs in the order given.
- `**Concerns:**` — first line of the body, listing every package a site has to
  install to be affected. Alphabetical, full composer names, `@pushword/js-helper`
  last. Add the packages your change touches to the line, keep the others.
- One `##` section per change, saying what breaks and what to do about it.

Several changes land here between two tags: append to the file, do not replace it.
-->

**Concerns:** `pushword/core`, `pushword/dev-app`, `pushword/installer`

## Pushword ships a Docker/FrankenPHP setup, and the installer asks before writing it

`composer create-project` now checks which of the [required extensions](/installation)
your PHP actually has and whether a Docker daemon answers, then asks once — recommending
the answer that fits the machine, with the reason. Say no and **not one Docker file is
written**. Nothing changes for an unattended install (CI, `--no-interaction`): it never
asks and never writes.

Existing sites are untouched — `install.php` does not re-run — but two things are worth
knowing if you want the setup.

**The recipe's `compose.yaml` is in the way.** `doctrine/doctrine-bundle` writes a
PostgreSQL service, and `symfony/mailer` adds Mailpit to `compose.override.yaml`: a stack
with no application service, for a database Pushword does not use. A new install now
deletes both. Yours still has them, and `pw:docker:init` will not overwrite a file you
might have edited:

```shell
rm compose.yaml compose.override.yaml
php bin/console pw:docker:init
```

**Your `Caddyfile` still works, but has not gained the placeholders.** The one shipped
now serves both a native `frankenphp run` and the container, through
`CADDY_SERVER_NAME`, `CADDY_ADMIN` and `FRANKENPHP_WORKER_CONFIG` — each falling back to
the local default when unset, so a native run behaves exactly as before. To pick it up:

```shell
cp vendor/pushword/dev-app/Caddyfile Caddyfile
```

`FRANKENPHP_WORKER_CONFIG` replaces the commented-out `worker` block: worker mode is
still opt-in, and still wants `runtime/frankenphp-symfony` first.

[Docker](/docker) covers the development and production stacks, what belongs in a volume
and how the first boot seeds itself.

## `ext-soap` was never required

It was in the requirement list on [Installation](/installation) and nothing in Pushword
or its dependencies has ever called it. Removed from the list, and absent from the check
the installer runs — a PHP without it was already running Pushword fine.
