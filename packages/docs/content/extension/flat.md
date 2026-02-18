---
title: 'Pushword Flat File CMS - Markdown and Twig Ready'
h1: Flat
publishedAt: '2025-12-21 21:55'
toc: true
---

Transform Pushword in a FlatFile CMS.

## Install

```
composer require pushword/flat
```

## Configure (if needed)

Globally under `flat:` (in `config/packages/flat.yaml`).

Or for _multi-sites_ in `config/packages/pushword.yaml` under the app configuration.

```yaml
flat:
  flat_content_dir: content # default value

  # Change detection cache TTL in seconds (default: 300 = 5 minutes)
  change_detection_cache_ttl: 300

  # Auto-export to flat files after admin modifications (default: true)
  auto_export_enabled: true

  # Use background process for deferred export (default: true)
  use_background_export: true

  # Editorial lock TTL in seconds (default: 1800 = 30 minutes)
  lock_ttl: 1800

  # Auto-lock when flat files are modified (default: true)
  auto_lock_on_flat_changes: true
```

## Usage

### Sync with DB (import / export)

```bash
php bin/console pw:flat:sync [host] [options]
```

**Options:**

| Option          | Description                                                   |
| --------------- | ------------------------------------------------------------- |
| `host`          | Optional host to sync (uses default app if not provided)      |
| `--mode`, `-m`  | Sync direction: `auto` (default), `import`, `export`          |
| `--entity`      | Entity type: `page`, `media`, `conversation`, `all` (default) |
| `--force`, `-f` | Force overwrite even if files are newer than DB               |
| `--skip-id`     | Skip adding IDs to markdown files and CSV indexes             |
| `--no-backup`   | Disable automatic database backup before import               |

**Examples:**

```bash
# Auto-detect: imports if flat files are newer, exports if DB is newer
php bin/console pw:flat:sync

# Force import (flat files → database)
php bin/console pw:flat:sync --mode=import

# Force export (database → flat files)
php bin/console pw:flat:sync --mode=export

# Sync only pages on a specific host
php bin/console pw:flat:sync example.tld --mode=import --entity=page

# Export without adding IDs to files
php bin/console pw:flat:sync --mode=export --skip-id

# Import without creating a database backup
php bin/console pw:flat:sync --mode=import --no-backup
```

### Editorial Lock System

The lock system prevents concurrent modifications between flat files and admin interface, inspired by LibreOffice's locking mechanism.

```bash
# Acquire a lock (shows warning in admin)
php bin/console pw:flat:lock [host] [--ttl=1800] [--reason="Editing flat files"]

# Release the lock
php bin/console pw:flat:unlock [host]
```

**How it works:**

- **Auto-lock**: When flat files are modified, an automatic lock is acquired (configurable)
- **Manual lock**: Use `pw:flat:lock` for explicit control during extended editing sessions
- **TTL**: Locks expire after 30 minutes by default (configurable)
- **Admin warning**: When locked, admin users see a warning message but can still edit (risk of conflict)

### Webhook Lock API

For CI/CD workflows and external systems, a REST API allows managing locks programmatically. Webhook locks are stricter than manual locks: they **block admin saves entirely** (not just a warning).

Authentication uses a **Bearer token** stored in the user's `apiToken` field.

| Endpoint           | Method | Description            |
| ------------------ | ------ | ---------------------- |
| `/api/flat/lock`   | POST   | Acquire a webhook lock |
| `/api/flat/unlock` | POST   | Release a webhook lock |
| `/api/flat/status` | GET    | Check lock status      |

**Examples:**

```bash
# Acquire a lock (default TTL: 1 hour)
curl -X POST https://example.com/api/flat/lock \
  -H "Authorization: Bearer {api_token}" \
  -H "Content-Type: application/json" \
  -d '{"host": "example.com", "reason": "Bulk update", "ttl": 7200}'

# Release a lock
curl -X POST https://example.com/api/flat/unlock \
  -H "Authorization: Bearer {api_token}" \
  -H "Content-Type: application/json" \
  -d '{"host": "example.com"}'

# Check status
curl "https://example.com/api/flat/status?host=example.com" \
  -H "Authorization: Bearer {api_token}"
```

**Key behaviors:**

- Webhook locks default to **1 hour TTL** (vs 30 minutes for manual/auto locks)
- Admin saves on locked pages throw an `AccessDeniedHttpException`
- `pw:flat:sync` is blocked entirely when a webhook lock is active
- Cannot override an existing non-expired webhook lock
- Only webhook locks can be released via the API (not manual/CLI locks)

### Conflict Resolution

When both flat files and database are modified since the last sync, a conflict occurs. The system uses a **"most recent wins"** strategy:

- The newer version (by timestamp) is kept
- The losing version is backed up as `filename~conflict-{id}.md`
- Conflicts are logged and displayed in admin

```bash
# List and clear conflict backup files
php bin/console pw:flat:conflicts:clear [host] [--dry]
```

**Conflict backup files:**

- Markdown: `page~conflict-abc123.md` (contains the losing version with a comment header)
- CSV: `index.conflicts.csv` (appends conflict details for media/conversation)

## Sync Behavior Reference

### Execution Pipeline

When `pw:flat:sync` runs, it follows this pipeline:

1. **Webhook lock check** — if a webhook lock is active for the host, sync is blocked entirely
2. **PID concurrency check** — only one sync process can run at a time (PID file in `var/`)
3. **Database backup** — before import, `var/app.db` is copied to `var/app.db~YYYYMMDDHHMMSS` (disable with `--no-backup`)
4. **Host resolution** — if no `host` argument, syncs ALL configured hosts sequentially
5. **Mode dispatch** — `auto` runs freshness detection then delegates to import or export

### Auto Mode Detection

In `auto` mode (the default), the system decides whether to import or export:

**For pages:** scans all `.md` files recursively. If any file's `mtime` is newer than its matching page's `updatedAt` in the database, or if a `.md` file has no corresponding page, it triggers **import**. Otherwise it triggers **export**.

**For media:** compares SHA-1 file hashes against stored hashes in the database. If any file's hash differs from the DB, or if a file has no matching media entity, it triggers **import**. Otherwise it triggers **export**.

Non-`.md` files (`.txt`, `.csv`, etc.) do NOT influence page auto-detection. Only `.md` files are considered.

### Concurrency Prevention

- A **PID file** is created in `var/` when sync starts
- If another `pw:flat:sync` is already running (PID file exists and process is alive), the command exits immediately
- **Stale PID files** (process no longer running) are automatically cleaned up
- The PID file is removed in a `finally` block after sync completes

### Page Sync

#### Import (flat to database)

1. **Redirections first**: `redirection.csv` is loaded and imported before pages
2. **Markdown import**: all `.md` files are parsed (YAML frontmatter + body)
3. **Deferred properties**: `parentPage`, `translations`, `extendedPage` are resolved after all pages exist
4. **Deletion**: pages in DB with no matching `.md` file AND no matching `redirection.csv` row are **deleted**
5. **Index regeneration**: `index.csv` / `iDraft.csv` are regenerated to reflect DB state

**Important behaviors:**

- `index.csv` is **read-only during import** — editing it has no effect; `.md` files are the source of truth
- Backup files (`*.md~`) are **ignored** during import
- With `--force`, ALL host pages are **deleted before importing** (fresh start)
- `publishedAt: draft` in frontmatter maps to `null` (unpublished)

#### Export (database to flat)

1. Each page is exported as a `.md` file with YAML frontmatter
2. Pages with redirections go to `redirection.csv` (their `.md` files are deleted)
3. Published pages are listed in `index.csv`, drafts in `iDraft.csv`
4. **Smart skip**: if exported content matches existing file content, the file is not rewritten
5. File `mtime` is synced to `page.updatedAt` to prevent false freshness detection on next auto run

### Media Sync

#### Import (flat to database)

1. **CSV index loaded** from `content/media.csv` (local filesystem, not Flysystem)
2. **File validation**: checks that files referenced in CSV actually exist
3. **Rename preparation**: if CSV has different `fileName` for an existing ID, the file is renamed in storage
4. **Hash-based rename detection**: if a file on disk has the same SHA-1 hash as a missing media entity, the existing entity's filename is updated (no duplicate created)
5. **Duplicate detection**: if a new file has the same SHA-1 hash as an existing media, the duplicate file is deleted and the existing entity's `fileNameHistory` is updated
6. **Deletion**: media entities in DB whose IDs are NOT in the CSV are **deleted** (only if CSV contained IDs)
7. **Storage import**: files from the Flysystem storage are imported (hash-based skip for unchanged)
8. **Local dir import**: files from `{content_dir}/media/` are copied to storage then imported
9. **Oversized image resize**: images exceeding 1980x1280 are automatically resized down (preserving aspect ratio), then background cache generation is triggered
10. **Index regeneration**: `content/media.csv` is regenerated to reflect DB state

#### Media Edge Cases

- **New file in media dir, not in CSV** — imported as new media entity. CSV regenerated to include it.
- **File deleted from disk, CSV row remains** — the missing file is removed from the index, so its ID is no longer tracked. The media entity is **deleted from DB**. Row removed from regenerated CSV.
- **CSV row removed, file still exists** — media entity **deleted from DB**. Physical file also **deleted** via Doctrine's `preRemove` listener.
- **File renamed on disk, CSV not updated** — hash-based detection: SHA-1 hash is compared against media with missing files. If a match is found, the existing entity's filename is updated (no duplicate created).
- **Filename changed in CSV (same ID), file not renamed** — file is **automatically renamed** in storage via `prepareFileRenames()`.
- **File content modified** — SHA-1 hash differs from DB — media re-imported with updated hash.
- **Lock/temp files** (`.~lock.*`, `~$*`) — **always skipped**, never imported.
- **New file with same hash as existing media** — duplicate detected by SHA-1 hash. The new file is **deleted**, and the existing entity's `fileNameHistory` is updated. No new entity created.
- **Oversized image imported** — images exceeding 1980x1280 are automatically resized down (preserving aspect ratio). Background cache generation is triggered for responsive variants + WebP.
- **Duplicate filenames in CSV** — handled gracefully, first entry wins.

#### content/media.csv Format

```csv
id,fileName,alt,tags,width,height,ratio,fileNameHistory,alt_en,alt_fr
1,image.jpg,Base alt,photo,800,600,1.33,old-image.jpg,English alt,French alt
2,doc.pdf,A document,document,,,,,
```

- `fileNameHistory`: comma-separated previous filenames (for rename tracking)
- `alt_*` columns: localized alt texts, auto-detected by locale suffix
- Extra columns are stored as custom properties

### User Sync

User sync is **opt-in**: it only activates when `config/users.yaml` exists. If the file is missing, user sync is skipped entirely.

```yaml
users:
  - email: admin@example.tld
    roles: [ROLE_SUPER_ADMIN]
    locale: en
    username: Admin
```

- **YAML is the source of truth** — users not in YAML are deleted from the database
- **Passwords are never synced** — they remain DB-only
- New YAML users are created without passwords (use magic link auth)
- Existing users are updated (roles, locale, username) but password is preserved
- If `config/users.yaml` does not exist, no users are created, updated, or deleted

### Idempotency

Running sync twice in a row with no changes produces **zero operations**:

- **Page import**: files whose `mtime` is older than page's `updatedAt` are skipped
- **Page export**: files whose content matches DB content are skipped (smart diff)
- **Media import**: files whose SHA-1 hash matches DB hash are skipped
- **Media export**: CSV is regenerated but reflects identical data

### Database Backup

Before any import operation, the SQLite database is backed up:

- Backup file: `var/app.db~YYYYMMDDHHMMSS`
- Disable with `--no-backup`
- To restore: copy the backup file back to `var/app.db`

### Multi-Host Sync

When running without `--host`:

- ALL configured hosts are synced sequentially
- Each host has its own content directory, lock file, and sync state
- Pages from host A are never written to host B's content directory
- Sync state (timestamps, conflicts) is tracked **per host**

## Generate AI index

```bash
php bin/console pw:ai-index [host] [exportDir]
```

Generate two CSV files (`pages.csv` and `medias.csv`) with metadata useful for AI tools.

Where:

- `host` is optional (uses default app if not provided)
- `exportDir` is optional (uses `flat_content_dir` by default)

### `pages.csv`

Contains page metadata with the following columns:

- `slug` - Page slug
- `h1` - Page H1 title
- `createdAt` - Creation date (Y-m-d H:i:s)
- `tags` - Page tags
- `summary` - Page summary/excerpt
- `mediaUsed` - Comma-separated list of media files used in the page
- `parentPage` - Parent page slug (if any)
- `pageLinked` - Comma-separated list of page slugs linked in the content
- `length` - Content length in characters

### `medias.csv`

Contains media metadata with the following columns:

- `media` - Media filename
- `mimeType` - MIME type
- `name` - Media name
- `usedInPages` - Comma-separated list of page slugs using this media

### Write

By default, the content is organized in `content/%main_host%/` and images in `content/%main_host%/media` or in `media/`. Media metadata is stored in `content/media.csv` (global, not per-host).

Example structure:

```
content/
content/media.csv
content/homepage.md
content/kitchen-sink.md
content/other-page.md
content/en/homepage.md
content/en/kitchen-sink.md
content/media/illustration.jpg
```

#### `kitchen-sink.md` example:

```yaml

---

h1: 'Welcome in Kitchen Sink'
locale: fr
translations:
  - en/kitchen-sink
mainImage: illustration.jpg
parentPage: homepage
metaRobots: 'no-index'
name: 'Kitchen Sink'
title: 'Kitchen Sink - best google result'
tags: 'demo example'
publishedAt: '2025-01-15 10:00'

---

My Page content Yeah !
```

**Key points:**

- Both **camelCase** and **underscore_case** work for property names (`parentPage` and `parent_page` are equivalent)
- `parent` is automatically normalized to `parentPage`
- Links to pages must use **slug** references
- **slug** is derived from the file path (removing `.md`) and can be overridden with a `slug` property in the frontmatter
- `homepage` can be named `index.md` or `homepage.md`
- `publishedAt: draft` sets the page as unpublished (`null` in DB)
- Unknown properties are stored in `customProperties`
- `mainImage` references a media filename (not a path)

### Translations (hreflang) Sync

The `translations` property handles the bidirectional many-to-many relationship between pages for internationalization (hreflang).

**Key behaviors:**

- **Addition takes precedence**: If page A declares page B as a translation, the link is created even if B.md doesn't list A. You only need to add the translation in one file.
- **No translations key = no changes**: If a page's markdown file doesn't have a `translations` property, existing translations in the database are preserved unchanged.
- **Explicit empty array removes all**: Use `translations: []` to explicitly remove all translations from a page.

**Examples:**

```yaml
# In fr/about.md - adds en/about as translation

---

translations:
  - en/about

---

# In en/about.md - no translations key, existing links preserved

---

h1: About Us

---

```

With this setup, both pages will be linked as translations of each other after sync.

To remove a translation, you must explicitly set an empty array in **both** files, or remove the translation from **one** file while the other file doesn't have a translations key (letting the removal propagate).

### Common Tasks

Here is what happens for typical editing workflows after running `pw:flat:sync --mode=import`:

#### Pages

- **Create a new `.md` file** — a new page is created in the database. It appears in `index.csv` (or `iDraft.csv` if `publishedAt: draft`).
- **Edit a `.md` file** — the page is updated in the database (the file's `mtime` must be newer than the page's `updatedAt`).
- **Delete a `.md` file** — the page is **deleted from the database**. Its row is removed from `index.csv`.
- **Rename a `.md` file** — the old page is deleted and a new one is created with the slug derived from the new filename. To keep the same page, use the `slug` property in frontmatter instead.
- **Edit `index.csv`** — **nothing**. `index.csv` is read-only during import. It is regenerated after every import to reflect the database state. Edit `.md` files instead.
- **Edit `redirection.csv`** — redirections are imported. Pages matching a redirection slug are converted to redirects.

#### Media

- **Drop a new image into `media/`** — the image is imported as a new media entity. If it exceeds 1980x1280 pixels, it is automatically resized down. A new row appears in `content/media.csv` after sync.
- **Drop a duplicate image** (same content as an existing media) — the duplicate file is **deleted**. The existing media's `fileNameHistory` is updated. No new entity is created.
- **Replace an image** (same filename, different content) — the media entity is updated with the new file's hash, dimensions, and size.
- **Delete an image from disk** — the media entity is **deleted from the database**. The row is removed from `content/media.csv` on next sync.
- **Rename an image on disk** (without editing CSV) — hash-based detection matches the renamed file to the missing media entity and updates its filename. No duplicate is created.
- **Edit `content/media.csv` — change `alt` or `tags`** — the media entity is updated with the new values.
- **Edit `content/media.csv` — change `fileName` (same ID)** — the file is **automatically renamed** in storage to match the new name.
- **Edit `content/media.csv` — remove a row** — the media entity is **deleted from the database** and the physical file is also deleted.
- **Edit `content/media.csv` — add a row for an existing file** — the file is imported as a new media entity with the provided metadata. If the file does not exist on disk, the row is silently ignored and removed from the regenerated CSV.

#### Users

- **Create `config/users.yaml`** — enables user sync (opt-in). Without this file, no users are touched.
- **Add a user to `config/users.yaml`** — a new user is created (without password — use magic link auth to set one).
- **Change roles/locale/username in YAML** — the existing user is updated. Password is preserved.
- **Remove a user from YAML** — the user is **deleted from the database**.
- **Delete `config/users.yaml`** — disables user sync entirely. No users are created, updated, or deleted.

## Media Optimization on Import

When importing **new images**, flat import applies the same optimization pipeline as admin upload:

- Scales down oversized images to max 1980x1280 pixels (server-side)
- Generates responsive variants (xs, sm, md, lg, xl) + WebP conversion (background)
- Extracts dominant color for placeholders (background)
- Runs lossless compression with optipng, jpegoptim, etc. (background)

**Not automatic:** PDF optimization (Ghostscript compression + qpdf linearization) is not triggered during flat import. Use the commands below.

### Manual optimization commands

```bash
# Regenerate image cache (responsive variants + WebP) for all or updated media
php bin/console pw:image:cache

# Optimize images (lossless compression)
php bin/console pw:image:optimize

# Optimize PDFs (requires ghostscript and/or qpdf)
php bin/console pw:pdf:optimize
```