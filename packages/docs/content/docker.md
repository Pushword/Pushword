---
title: 'Run Pushword with Docker and FrankenPHP'
h1: Docker
publishedAt: '2026-08-04 12:00'
name: Docker
parentPage: installation
toc: true
---

Pushword ships a Docker setup built on [FrankenPHP](https://frankenphp.dev/): PHP 8.4
with every extension Pushword needs, Caddy in front, and the image tooling the media
pipeline shells out to.

It is **optional**. A machine that already runs PHP 8.4 with the
[required extensions](/installation) runs Pushword faster and more directly without it —
`bin/console`, the profiler and Xdebug are all one command away. Docker earns its place
when installing those extensions is the hard part.

## Getting the files

`composer create-project` asks, once, whether to use Docker — and recommends the answer
that fits your machine, having checked which extensions your PHP actually has. Say no
and **nothing Docker-related is written**.

To add them later, or to a project created before the question existed:

```shell
php bin/console pw:docker:init
```

It writes `Dockerfile`, `compose.yaml`, `compose.prod.yaml`,
`compose.postgresql.yaml`, `.dockerignore` and
`docker/`, and never overwrites a file you have edited (`--force` if you want it to).

## Development

```shell
docker compose up --build
```

Then open <http://localhost:8080> (`HTTP_PORT` changes the port).

The project is bind-mounted, so your editor stays the source of truth:

```shell
docker compose exec pushword php bin/console pw:page:list
```

Only `var/cache` is kept inside the container — Symfony bakes absolute paths into its
compiled cache, so the host's and the container's cannot be the same directory.
Everything else in `var/`, **`var/app.db` above all**, stays on the host where you can
back it up.

### PostgreSQL

The optional overlay replaces the Doctrine database with PostgreSQL while keeping the
default stack unchanged:

```shell
POSTGRES_PASSWORD='choose-a-password' \
  docker compose -f compose.yaml -f compose.postgresql.yaml up --build
```

The same overlay works in production:

```shell
POSTGRES_PASSWORD='choose-a-password' \
  docker compose -f compose.prod.yaml -f compose.postgresql.yaml up -d --build
```

## Production

```shell
docker compose -f compose.prod.yaml up -d --build
```

Put your settings in a `.env` next to it:

```dotenv
CADDY_SERVER_NAME=example.com
HTTP_PORT=80
HTTPS_PORT=443
```

`CADDY_SERVER_NAME` is the one that matters: give it a domain rather than a port and
Caddy obtains and renews the TLS certificate itself.

### What is in a volume, and why

The default SQLite stack keeps its state on disk. The production stack puts each of
those directories in a named volume:

| Volume | Path | Holds |
|---|---|---|
| `var` | `/app/var` | the SQLite database, cache, logs |
| `media` | `/app/media` | the originals you uploaded |
| `media_cache` | `/app/public/media` | generated derivatives — regenerable |
| `static` | `/app/static` | [static-generator](/extension/static-generator) exports |
| `caddy_data` | `/data` | TLS certificates |
| `postgres_data` | `/var/lib/postgresql/data` | PostgreSQL data, when the overlay is used |

Delete `var` or `media` and you delete content. Back them up as you would any database:

```shell
docker compose -f compose.prod.yaml cp pushword:/app/var/app.db ./app.db.backup
```

With PostgreSQL, use its native backup tools instead:

```shell
docker compose -f compose.prod.yaml -f compose.postgresql.yaml \
  exec -T postgres pg_dump -U pushword -d pushword > pushword.sql
```

### First boot

Every boot runs `doctrine:schema:update --force` — Pushword has no migrations, the
schema is derived from the entities — then `assets:install` and `cache:clear`.

On top of that, an empty `var` volume gets **one super admin account**, because an
instance with no account cannot be logged into. Set the credentials before that first
boot:

```dotenv
PUSHWORD_ADMIN_EMAIL=you@example.com
PUSHWORD_ADMIN_PASSWORD=…
```

Leave them unset and the entrypoint generates a random temporary password, prints it
once in the first-boot logs, and requires it to be changed before administration can
be accessed. Set `PUSHWORD_ADMIN_PASSWORD` when a provisioning system already owns
secret generation and delivery.

It creates **no content**. Production content is yours, and it arrives the way you
deploy it — a database backup you restore, or Markdown files
[pw:flat:sync](/extension/flat) reads. The demo pages `composer create-project` installs
are development content and stay on your machine.

The account is created once: the entrypoint then drops a `var/.pushword-seeded` marker.
A development project is marked by `pw:docker:init` itself, since it was already
installed on the host.

A restored backup never gains an account, whatever address its own admin uses. The
marker alone could not promise that — a database restore into a fresh volume arrives
without it — so before creating anything the
entrypoint asks the database whether it holds any user at all, and says so in the logs
when it does.

## Worker mode

The container runs classic per-request PHP. To switch it to a long-running kernel,
install the runtime and set one variable:

```shell
composer require runtime/frankenphp-symfony
```

```dotenv
FRANKENPHP_WORKER_CONFIG=worker ./public/index.php 1
```

Pushword is safe to run this way; [Performance](/performance) explains why, and what the
gain actually depends on.

## The Caddyfile

There is only one, at the root of your project, and it serves both a native
`frankenphp run --config Caddyfile` and the container. The container overrides its
placeholders — `CADDY_SERVER_NAME`, `CADDY_ADMIN`, `FRANKENPHP_WORKER_CONFIG` — through
the environment; unset, each falls back to the local default. Edit it once and both
paths follow.

## Notes

- The image runs as `www-data` in production. Fresh named volumes inherit their
  ownership from the image, which is why the four state directories are created there.
- Assets are not built in the image. A default install needs no build — the bundles ship
  their CSS and JS. A project with custom assets should run `yarn build` before
  `docker compose -f compose.prod.yaml build`, or add a Node stage to the `Dockerfile`.
- `svgo` is not installed, so SVGs pass through unoptimized. Every other optimizer in the
  chain (`cjpeg`, `pngquant`, `optipng`, `gifsicle`, `cwebp`) is there.
- Both supplied PHP configurations set `expose_php=Off`, so PHP does not emit an
  `X-Powered-By` version header.
