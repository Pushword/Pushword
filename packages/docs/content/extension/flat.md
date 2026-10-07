---
title: 'Pushword Flat File CMS - Markdown and Twig Ready'
h1: Flat
publishedAt: '2025-12-21 21:55'
toc: true
---

Turn Pushword into a flat-file CMS: pages, media, snippets and conversations round-trip
between Markdown/CSV files and the database.

## Install

```
composer require pushword/flat
```

## Configure (if needed)

In `config/packages/flat.yaml`. Only `flat_content_dir` can also be set per site, in the
app configuration of `config/packages/pushword.yaml`.

```yaml
pushword_flat:
  flat_content_dir: '%kernel.project_dir%/content/_host_' # default; _host_ = the site's main host

  # Optional shared secret for read-only snapshot downloads
  content_snapshot_key: '%env(CONTENT_SNAPSHOT_KEY)%'

  # Change detection cache TTL in seconds (default: 300)
  change_detection_cache_ttl: 300

  # Auto-export to flat files after admin modifications (default: true)
  auto_export_enabled: true

  # Git commit and push content changes after export (default: false)
  auto_git_commit: false

  # Debounce delay in seconds before processing deferred export (default: 120)
  export_debounce_delay: 120

  # Editorial lock TTL in seconds (default: 1800)
  lock_ttl: 1800

  # Webhook lock TTL in seconds (default: 3600)
  webhook_lock_default_ttl: 3600

  # Auto-lock when flat files are modified (default: true)
  auto_lock_on_flat_changes: true

  # File basenames never imported as pages (default below)
  exclude_files: ['AGENTS.md', 'CLAUDE.md', 'README.md']

  # Custom property names to exclude from flat file export and import (default: [])
  ignored_properties: ['someTransientProp']

  # Who a page created by an import belongs to when its file names no editor
  # (default: null, meaning the site's first super admin)
  default_editor: editor@example.tld

  # Conflict and sync error emails (default: none)
  notification_email_recipients: ['admin@example.tld']
  notification_email_from: noreply@example.tld
```

### Who an imported page belongs to

A `pw:flat:sync` run has no authenticated user, so the editor comes from the file. An
export writes it there as an email:

```yaml
editedBy: editor@example.tld
createdBy: editor@example.tld
```

On import those resolve back to the matching user. An email no user answers to is
ignored — the page keeps whichever editor it already had.

A file naming nobody is attributed, **at creation only**, to `default_editor` or failing
that the site's first super admin. Later syncs of that file leave the editor alone
(`editMessage` already records that the edit came from `pw:flat:sync`). With no user to
resolve, the page stays unattributed.

## Usage

### Read-only content snapshot

Set `content_snapshot_key` to let an automated client download the current Markdown
mirror without a Pushword user. Keep the secret in an environment variable and send it
in the dedicated header:

```bash
curl -H "X-Pushword-Snapshot-Key: $CONTENT_SNAPSHOT_KEY" \
  "https://example.com/api/content/snapshot.tar.gz?host=example.com" \
  --output snapshot.tar.gz
```

Omit `host` to download every configured site. The key is accepted only by
`GET /api/content/snapshot.tar.gz`; it cannot authenticate any other route or write
content. Editor Bearer tokens are accepted too.

A `HEAD` request (`curl -I`) checks a key: same status as `GET`, without re-exporting
the mirror.

### Sync with DB (import / export)

```bash
php bin/console pw:flat:sync [host] [options]
```

| Option              | Description                                                                     |
| ------------------- | ------------------------------------------------------------------------------- |
| `host`              | Host to sync; omit to sync every configured host                                |
| `--mode`, `-m`      | Sync direction: `auto` (default), `import`, `export`                            |
| `--entity`          | `page`, `media`, `conversation`, `snippet`, `user`, `all` (default)             |
| `--page`            | Slug(s) to sync — repeatable, implies `--entity=page` (see targeted sync below) |
| `--force`, `-f`     | Force overwrite even if files are newer than DB                                 |
| `--backup`          | Back up SQLite before import (server databases require their native tools)      |
| `--consume-pending` | Consume pending export flag and run batched export                              |
| `--format`          | `auto` (default), `agent` (JSON) or `text` — see [agent output](/agent-output)  |

```bash
# Auto-detect: imports if flat files are newer, exports if DB is newer
php bin/console pw:flat:sync

# Force a direction
php bin/console pw:flat:sync --mode=import
php bin/console pw:flat:sync --mode=export

# Only pages on a specific host
php bin/console pw:flat:sync example.tld --mode=import --entity=page

# Import after creating an SQLite backup
php bin/console pw:flat:sync --mode=import --backup
```

`pw:flat:watch` polls the content directory and syncs on change (`--interval`, default
0.5 s; `--mode=import` to never export). With `--live-reload`, pages served in debug
mode reload in the browser after each sync.

#### Revision stamp & re-stamping

Each exported `.md` carries a `revision: <hash> # read only` front matter line — the
API's ETag / `If-Match` value, so an agent can `PUT` the file back without a preliminary
`GET`. It is written on export and **ignored on import**.

A normal export skips unchanged files, so a file missing the line is not re-stamped by a
plain sync. Force a full-host export to rewrite them (files already correct stay
byte-identical):

```bash
php bin/console pw:flat:sync example.tld --mode=export --force
```

`--force` combined with `--page` does **not** re-stamp.

#### Targeted page sync (`--page`)

Restrict the sync to specific slugs — much faster on a large multi-site setup:

```bash
php bin/console pw:flat:sync example.tld --page=about --page=contact --mode=import
```

Differences from a full sync:

- Only the listed slugs are imported or exported — all other pages are untouched
- `deleteMissingPages` is **skipped**: pages absent from the filter are never deleted, even if their `.md` file is missing
- Redirections (`redirection.csv`) are **not re-imported**
- `--force` re-imports only the targeted pages regardless of timestamps; it does not reset the host

### Deferred Export & Git Auto-Commit

An admin save dispatches a Messenger message delayed by `export_debounce_delay`. Each new
save resets the timer, so rapid edits are batched into a single export (and commit). A
worker must be running:

```bash
php bin/console messenger:consume async -v
```

Or consume pending exports manually:

```bash
php bin/console pw:flat:sync --consume-pending
```

With `auto_git_commit: true`, each batched export is committed, pulled and pushed. The
content directory (or its parent) must be a git repository.

### Editorial Lock System

The lock prevents concurrent modifications between flat files and the admin.

```bash
# Acquire a lock (shows warning in admin)
php bin/console pw:flat:lock [host] [--ttl=1800] [--reason="Editing flat files"]

# Release the lock
php bin/console pw:flat:unlock [host]
```

- **Auto-lock**: acquired when flat files are modified (`auto_lock_on_flat_changes`)
- **Manual lock**: `pw:flat:lock`, for extended editing sessions
- **TTL**: `lock_ttl`, 30 minutes by default
- **Admin warning**: admin users see a warning but can still edit (risk of conflict)

### Webhook Lock API

For CI/CD and external systems. Webhook locks are stricter than manual locks: they **block
admin saves entirely**. Authentication uses the user's `apiToken` as a Bearer token.

| Endpoint           | Method | Description            |
| ------------------ | ------ | ---------------------- |
| `/api/flat/lock`   | POST   | Acquire a webhook lock |
| `/api/flat/unlock` | POST   | Release a webhook lock |
| `/api/flat/status` | GET    | Check lock status      |

- Default TTL is `webhook_lock_default_ttl` (1 hour)
- Admin saves on locked pages throw an `AccessDeniedHttpException`
- `pw:flat:sync` is blocked entirely while a webhook lock is active
- An existing non-expired webhook lock cannot be overridden
- Only webhook locks can be released via the API (not manual/CLI locks)

Request examples, CI pipelines and the status response: [Git workflow](/extension/flat-git-workflow).

### Conflict Resolution

When a page's file and its database row were both modified since the last sync, **the
most recent wins**:

- The losing version is backed up next to the file as `page~conflict-{id}.md`, with a comment header
- An admin notification is created and emailed to `notification_email_recipients`

```bash
# List and clear conflict backup files
php bin/console pw:flat:conflicts:clear [host] [--dry]
```

### YAML Front Matter Validation

Invalid YAML in a `.md` file (e.g. an unescaped quote: `title: 'La Baltique : d'Usedom'`)
is a common authoring mistake. Check before syncing:

```bash
php bin/console pw:flat:lint [host]
```

Exit code `0` if all files are valid, `1` otherwise; each error shows the file path and line.

During `pw:flat:sync`, a file with invalid YAML is **skipped** with its error printed,
its DB page is **not deleted**, and a final warning counts the skipped files.

| Mistake                          | Fix                                       |
| -------------------------------- | ----------------------------------------- |
| `title: 'It's broken'`           | Use double quotes: `title: "It's fine"`   |
| `title: A: B` (unquoted colon)   | Quote the value: `title: 'A: B'`          |
| Smart quotes `'` from copy-paste | Replace with straight quotes `'` or `"`   |

## Sync Behavior Reference

### Execution Pipeline

1. **Webhook lock check** — an active webhook lock for the host blocks the sync
2. **PID check** — only one sync runs at a time (PID file in `var/`; a stale one is cleaned up)
3. **Optional database backup** — see [Database Backup](#database-backup)
4. **Host resolution** — without a `host` argument, every configured host is synced sequentially, each with its own content directory, lock and sync state
5. **Mode dispatch** — `auto` runs freshness detection then delegates to import or export

### Auto Mode Detection

**Pages:** if any `.md` file's `mtime` is newer than its page's `updatedAt`, or a `.md`
file has no page, the sync **imports**; otherwise it **exports**. Other files (`.txt`,
`.csv`…) are ignored.

**Media:** if any file's SHA-1 hash differs from the stored hash, or a file has no media
entity, the sync **imports**; otherwise it **exports**.

### Page Sync

#### Import (flat to database)

1. **Redirections first**: `redirection.csv` is imported before pages
2. **Markdown import**: all `.md` files are parsed (YAML frontmatter + body)
3. **Deferred properties**: `parentPage`, `translations`, `extendedPage` are resolved after all pages exist
4. **Deletion**: pages with no matching `.md` file AND no matching `redirection.csv` row are **deleted**
5. **Index regeneration**: `index.csv` / `index.draft.csv` are regenerated from the database

- `index.csv` is **read-only** — `.md` files are the source of truth
- Backup files (`*.md~`) are ignored
- With `--force` (without `--page`), ALL host pages are **deleted before importing**
- A page file is a complete document: removing a canonical frontmatter property resets it to its default, and removing a custom property deletes it. `publishedAt` and [`translations`](#translations-hreflang-sync) keep their explicit reset syntax.
- `publishedAt: draft` maps to `null` (unpublished)

#### Export (database to flat)

1. Each page is exported as a `.md` file with YAML frontmatter
2. Pages with redirections go to `redirection.csv` (their `.md` files are deleted)
3. Published pages are listed in `index.csv`, drafts in `index.draft.csv`
4. A file whose content is unchanged is not rewritten
5. File `mtime` is set to `page.updatedAt` so the next auto run does not see it as newer

### Internal redirects (`redirectFrom`)

Like Jekyll's `redirect_from`, a page declares the old paths that redirect **to** it:

```yaml
---
h1: CMS comparison
redirectFrom:
  cms-comparison: 301
  old/comparison: 302
---
```

- A `{ oldPath: httpCode }` map. A bare list (`- cms-comparison`) is accepted on import
  as `301`. Paths are scoped to the page's host.
- Served at runtime and by the static generator (`.htaccess`, `Caddyfile`, meta-refresh
  stubs for GitHub Pages), like `redirection.csv` entries. Each matches its exact path,
  trailing slash optional: `old` never redirects `old/child`.
- A slug rename appends the old slug to the page's `redirectFrom`.
- Internal links to an old path are rewritten at render to the current slug
  (`[x](/old-name)` → `<a href="/new-name">`), with no 301 hop.
- `redirection.csv` keeps the redirects with **no destination page**: external targets,
  non-resolving paths, and chains.

Convert a site's existing internal redirect pages into `redirectFrom` (database-level,
with or without flat sync):

```bash
php bin/console pw:redirect:migrate [host] [--dry-run]
```

### Media Sync

#### Import (flat to database)

1. **CSV index**: `media.csv`, read from the content base dir (shared by all hosts)
2. **File validation**: rows whose file does not exist are dropped from the index
3. **Deletion**: media whose `fileName` is NOT in the CSV are **deleted**, with their file (only when the CSV holds at least one row)
4. **Storage import**: files from the Flysystem storage (hash-based skip for unchanged content)
5. **Local dir import**: files from `{content_dir}/media/` are copied to storage then imported
6. **Index regeneration**: `media.csv` is regenerated from the database

While importing a file:

- **Same hash as a media whose file is missing** — a rename: the entity's filename is updated
- **Same hash as an existing media** — a duplicate: the new file is deleted, the existing entity's `fileNameHistory` is updated
- **Unchanged file** — its CSV row is still compared with the database: `alt`, `tags`, `alt_*` and custom properties are updated when they differ
- **New or oversized image** — see [Media Optimization on Import](#media-optimization-on-import)
- **Lock/temp files** (`.~lock.*`, `~$*`) — always skipped

#### media.csv Format

`media.csv` lives in the content base dir (next to the per-host directories): media are
global, not owned by a host.

```csv
fileName,alt,tags,width,height,ratio,fileNameHistory,updatedAt,alt_en,alt_fr
image.jpg,Base alt,photo,800,600,1.33,old-image.jpg,2025-01-31 14:02:11,English alt,French alt
doc.pdf,A document,document,,,,,2025-01-30 09:11:40,,
```

- `fileName` identifies the row — there is no `id` column; a duplicate row is skipped (first wins)
- `alt`, `tags`, `alt_*` (localized alts, detected by locale suffix) and any extra column (stored as a custom property) are **imported back**
- An empty cell leaves the stored value alone; it does not erase it
- `width`, `height`, `ratio`, `fileNameHistory` and `updatedAt` are **read-only**: derived from the file, never imported

### User Sync

User sync is **opt-in**: it runs only when `config/users.yaml` exists (with
`pw:flat:sync`, or alone with `pw:flat:user-sync`).

```yaml
users:
  - email: admin@example.tld
    roles: [ROLE_SUPER_ADMIN]
    locale: en
    username: Admin
```

- **YAML is the source of truth** — users not in YAML are **deleted** from the database
- **Passwords are never synced** — new users are created without one (use magic link auth); existing users keep theirs
- Roles, locale and username of existing users are updated
- Delete `config/users.yaml` to disable user sync: no user is created, updated or deleted

### Idempotency

Running sync twice in a row with no changes produces **zero operations**: page import
skips files older than `updatedAt`, page export skips unchanged content, media import
skips matching hashes, and the regenerated `media.csv` is identical.

### Database Backup

Pass `--backup` to back up the SQLite database before an import:

- Backup file: `var/app.db~YYYYMMDDHHMMSS`; the ten most recent are kept
- To restore: copy the backup file back to `var/app.db`
- `php bin/console pw:backup --clean` keeps only the newest backup immediately

The option fails before importing on PostgreSQL or MariaDB: back up with the database
server's own tools, then sync without `--backup`.

### Renaming a host

An import matches pages on host and slug, so files imported under a host the database
does not know yet become new pages, with new ids, while the old host's rows stay behind.
Rename in the database first:

1. Update the `host` column from the old host to the new one in `page` and in every other
   table carrying one (snippets, conversation messages, quiz results, social posts, the
   version log…), plus the newsletter's `main_host`, `optin_host` and JSON `hosts` columns.
2. Change the host in `config/packages/pushword.yaml` and rename its content directory
   (`content/<old host>/` by default) and its template overrides (`templates/<old host>/`),
   if any. On a multi-host site, also rewrite the `<old host>/<slug>` entries in the other
   hosts' `translations` front matter: an import that cannot resolve one drops the link.
3. Delete both hosts' sync state, `var/flat-sync/<host>*.json` (dots become underscores):
   a stale state decides the direction of the next sync.
4. Run `pw:flat:sync <new host>`.

A static build then writes to a new directory, since the default `static_dir` is
`static/{main_host}`: the old one stays behind.

## Deploying a site: `vendor/bin/pushword-deploy`

The bundle ships a site-agnostic deploy script. Per-site specifics — remote, SSH options,
excludes, local and remote command chains — live in a `deploy.conf` at the site root,
found upward from wherever you run it:

```bash
vendor/bin/pushword-deploy pull       # prod -> local
vendor/bin/pushword-deploy push       # full deploy: local prep, rsync, remote build
vendor/bin/pushword-deploy publish    # content/ + media/ only, no composer/build
vendor/bin/pushword-deploy pull-db    # SQLite only: fetch prod var/app.db (backs up local first)
# -n / --dry-run everywhere; SQLite only: --ship-db for the one-time rc802 migration
```

The push **always** excludes `var/app.db*` and `var/flat-sync/`, whatever the configured
excludes say: production owns its database (messages, quiz results, newsletter contacts,
admin edits between two pulls) and each machine owns its sync state. `--ship-db`
(confirmation required) sends the database file explicitly — only for the one-time uuid
migration.

`pull-db` and `--ship-db` are SQLite-only. On PostgreSQL or MariaDB, `pull`, `push` and
`publish` leave the server database untouched; use its native dump/restore tools.

With `DELETE=1`, the push first probes (`--dry-run`, same excludes) what `--delete` would
remove on production. Files that exist only there are usually prod-side work not yet
pulled, so the push lists them and asks for confirmation before removing anything. rsync
has no conflict detection — the last machine to sync wins — so this probe is what makes
`--delete` safe to keep on.

`DELETE=1` applies to `pull` too, with no probe: the pull removes every local file
production lacks, outside `PULL_EXCLUDES`. A site keeping local-only files in the synced
tree (notes, scratch databases) must list them in `PULL_EXCLUDES` or stay at `DELETE=0`.
At `DELETE=0`, a page deleted locally keeps its `.md` on production, so production never
deletes it: remove the file there by hand.

Minimal `deploy.conf`:

```bash
REMOTE="user@server"
REMOTE_PATH="/home/user/mysite.com"
# SSH_OPTS="-p 5022"                 # DELETE=1 for rsync --delete
# PULL_EXCLUDES / PUSH_EXCLUDES      # arrays, sensible defaults provided
# PRE_PUSH=('php bin/console pw:flat:sync')
REMOTE_DEPLOY='composer update && php bin/console doctrine:schema:update --force && bin/console cache:clear && php bin/console pw:flat:sync && php bin/console pw:static'
# PUBLISH_PATHS=('content' 'media')  # REMOTE_PUBLISH, POST_DEPLOY_LOCAL
```

`publish` runs `pw:flat:sync && pw:static --incremental` remotely by default: the import
moves the render epoch for anything listing-relevant, and the incremental build
regenerates just that — including pruning the pages the sync deleted. Keep a plain
`pw:static` at the end of `REMOTE_DEPLOY`: after `cache:clear` incremental would rebuild
everything anyway, and the full build's lint-before-swap protects the new code.

`POST_DEPLOY_LOCAL` (typically an opcache-reset loop over the PHP hosts) runs even when
the push dies mid-remote-chain, since a deploy failing after `composer update` has
already rebuilt the container. Only a push aborted at a confirmation prompt — before
anything touched production — skips it. `publish` never runs it.

The default excludes keep every **generated output** out: `public/assets/` (yarn build),
`public/bundles/` (assets install) and `public/media/` (`pw:image:cache`) are rebuilt by
the remote chain, like `static/`. A site that does *not* rebuild one of them server-side
overrides `PUSH_EXCLUDES` without it.

## Generate AI index

`pw:ai-index` writes `pages.csv` and `medias.csv` for AI tools, into `flat_content_dir`
by default. See [AI content index](/ai-index).

## Write content

With the default `flat_content_dir`, a site's pages live in `content/{main_host}/`.
Images go in `content/{main_host}/media/` or in the storage directory `media/` at the
project root; both are scanned during import.

```
content/example.tld/homepage.md
content/example.tld/kitchen-sink.md
content/example.tld/en/homepage.md
content/example.tld/en/kitchen-sink.md
content/example.tld/media/illustration.jpg
```

`kitchen-sink.md`:

```yaml
---
h1: 'Welcome in Kitchen Sink'
locale: fr
translations:
  - en/kitchen-sink
mainImage: illustration.jpg
parentPage: homepage
metaRobots: 'noindex'
name: 'Kitchen Sink'
title: 'Kitchen Sink - best google result'
tags: 'demo example'
publishedAt: '2025-01-15 10:00'
---
My Page content Yeah !
```

- Property names work in **camelCase** or **underscore_case** (`parentPage` = `parent_page`); `parent` is normalized to `parentPage`
- The **slug** is the file path without `.md`, unless a `slug` property overrides it; links to pages use slugs
- The homepage can be named `index.md` or `homepage.md`
- `publishedAt: draft` sets the page as unpublished (`null` in DB)
- Unknown properties are stored in `customProperties`
- `mainImage` references a media filename (not a path)

### Dates and time zones

The export writes `publishedAt` and `holdPublicationAt` in the editorial timezone, with
its offset: `publishedAt: '2026-09-30 16:00+02:00'`. The offset pins the instant, so a
file that goes out and comes back never shifts.

- A date with an offset is stored at that exact instant.
- Quotes are optional: `publishedAt: 2026-09-30` reads like `publishedAt: '2026-09-30'`.
- A date without one is read in the editorial timezone. The hour the autumn change shows
  twice (`2026-10-25 02:30` in Paris) is its first occurrence, summer time: add `+01:00`
  for the second. An hour the spring change skips moves forward by an hour.

The editorial timezone defaults to the server's (PHP's `date.timezone`, UTC on most hosts).
Set it to read and write dates on your editors' clock — the admin's date fields, its
publish-now and schedule buttons and its list columns follow it too:

```yaml
pushword:
    editorial_timezone: Europe/Paris
```

The database stays in the server timezone: leave `date.timezone` alone. On an existing
site, set the key last: `pw:flat:sync` to take in pending file edits, then
`pw:flat:sync --mode=export --force` so every date carries its offset. A date left
without one shifts on its next import.

### Translations (hreflang) Sync

`translations` links pages across locales, in both directions:

- **Addition wins**: if A lists B, the link is created even if B.md doesn't list A — declare it in one file.
- **No `translations` key = no change**: existing links are preserved.
- **`translations: []` removes all** links of that page.

```yaml
# fr/about.md — links en/about
---
translations:
  - en/about
---
# en/about.md — no translations key, so the link above stands
---
h1: About Us
---
```

To remove a translation, set `translations: []` in **both** files, or drop it from one
file while the other has no `translations` key.

### Common Tasks

What happens on `pw:flat:sync --mode=import`:

#### Pages

- **Create a `.md` file** — a page is created and listed in `index.csv` (or `index.draft.csv` if `publishedAt: draft`).
- **Edit a `.md` file** — the page is updated (the file's `mtime` must be newer than the page's `updatedAt`).
- **Delete a `.md` file** — the page is **deleted from the database**.
- **Rename a `.md` file** — the old page is deleted and a new one created with the new slug. To keep the page, set the `slug` property instead.
- **Edit `index.csv`** — **nothing**: it is regenerated from the database. Edit `.md` files instead.
- **Edit `redirection.csv`** — redirections are imported; pages matching a redirection slug become redirects.

#### Media

- **Drop a new image into `media/`** (`content/{host}/media/` or the storage `media/`) — imported as a new media and added to `media.csv`.
- **Drop a duplicate image** (same content as an existing media) — the duplicate file is **deleted**; the existing media's `fileNameHistory` is updated.
- **Replace an image** (same filename, different content) — the media is updated with the new hash, dimensions and size.
- **Delete an image from disk** — the media is **deleted from the database** and its row from `media.csv`.
- **Rename an image on disk** (without editing CSV) — hash detection updates the existing media's filename. No duplicate.
- **Edit `alt`, `tags`, an `alt_*` or a custom column in `media.csv`** — the media is updated, though the file never changed. This is the flat-file way to fix an alt text.
- **Edit `fileName` in `media.csv`** — **do not**: the row no longer matches a file, and the media still carrying the old name is deleted together with its file. Rename the file on disk instead.
- **Remove a row from `media.csv`** — the media **and its file** are deleted.
- **Add a row for an existing file** — the file is imported with the row's metadata. A row whose file does not exist is ignored and dropped from the regenerated CSV.

## Media Optimization on Import

New images get the same pipeline as an admin upload:

- Scaled down to max 1980x1280 pixels (server-side)
- Responsive variants (xs, sm, md, lg, xl) + WebP (background)
- Dominant color for placeholders (background)
- Lossless compression with optipng, jpegoptim, etc. (background)

PDF optimization (Ghostscript + qpdf) is **not** triggered by flat import. Run it manually:

```bash
php bin/console pw:image:cache     # responsive variants + WebP, all or updated media
php bin/console pw:image:optimize  # lossless image compression
php bin/console pw:pdf:optimize    # requires ghostscript and/or qpdf
```
