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

Globally under `pushword_flat` (in `config/packages`).

Or for _multi-sites_ in `config/packages/pushword.yaml`.

```yaml
pushword_flat:
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
# Sync: automatically detects whether to import (files newer than DB) or export (DB newer or no files)
php bin/console pw:flat:sync [host]

# Import flat files into the database
php bin/console pw:flat:import [host]

# Export database content to flat files
php bin/console pw:flat:export [host] [exportDir] [--force] [--entity=TYPE]
```

Where:

- `host` is optional (uses default app if not provided)
- `exportDir` (for export only) is optional (uses `flat_content_dir` by default)
- `--force` (for export only) forces overwriting even if files are newer than DB
- `--entity=TYPE` filters sync to specific entity type: `page`, `media`, `conversation`, or `all` (default)

**Examples:**

```bash
# Sync only pages
php bin/console pw:flat:sync --entity=page

# Export only media
php bin/console pw:flat:export --entity=media --force

# Import only conversations
php bin/console pw:flat:import --entity=conversation
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

### Conflict Resolution

When both flat files and database are modified since the last sync, a conflict occurs. The system uses a **"most recent wins"** strategy:

- The newer version (by timestamp) is kept
- The losing version is backed up as `filename~conflict-{id}.md`
- Conflicts are logged and displayed in admin

```bash
# List and clear conflict backup files
php bin/console pw:flat:conflicts:clear [host] [--dry-run]
```

**Conflict backup files:**

- Markdown: `page~conflict-abc123.md` (contains the losing version with a comment header)
- CSV: `index.conflicts.csv` (appends conflict details for media/conversation)

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

By default, the content may be organized in `content/%main_host%/` dir and image may be in `content/%main_host%/media` or in `media`

Eg:

```
content
content/homepage.md
content/kitchen-skink.md
content/other-example-kitchen-sink.md
content/en/homepage.md
content/en/kitchen-skink.md
content/media/default/illustation.jpg
content/media/default/illustation.jpg.yaml
```

#### `kitchen-sink.md` may contain :

```yaml

---

h1: 'Welcome in Kitchen Sink'
locale: fr
translations:
  - en/kitchen-skink
main_image: illustration.jpg
images:
  - illustration.jpg
parent:
  - homepage
metaRobots: 'no-index'
name: 'Kitchen Sink'
title: 'Kitchen Sink - best google restult'
#created_at: 'now' # see https://www.php.net/manual/fr/datetime.construct.php
#updated_at: 'now'

---

My Page content Yeah !
```

Good to know :

- **camel case** or **undescore case** work
- link to page must use **slug**
- **slug** is generate from file path (removing `.md`) and can be override by a property in _yaml front_
- `homepage` 's file could be named `index.md` or `homepage.md`
- Other properties will be added to `customProperties`

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

## Media Optimization

When uploading media through the admin interface, Pushword automatically:

**For images:**

- Scales down to max 1980x1280 pixels (browser-side, before upload)
- Generates responsive variants (xs, sm, md, lg, xl)
- Converts to WebP format
- Extracts dominant color for placeholders

**For PDFs:**

- Compresses with Ghostscript (downsamples images to 150dpi)
- Linearizes with qpdf (fast first-page web streaming)

### Bulk optimization for flat file users

When importing media via flat files, server-side optimizations are skipped. The optimization commands work on **Media entities in the database**, so you must import first:

```bash
# 1. First, import flat files to database (registers media)
php bin/console pw:flat:import

# 2. Then generate image cache (responsive variants + WebP)
php bin/console pw:image:cache

# 3. Optimize images (lossless compression with optipng, jpegoptim, etc.)
php bin/console pw:image:optimize

# 4. Optimize PDFs (requires ghostscript and/or qpdf)
php bin/console pw:pdf:optimize
```

**One-liner:**

```bash
php bin/console pw:flat:import && php bin/console pw:image:cache && php bin/console pw:pdf:optimize
```

### Pre-import image resizing

Browser-side scaling (1980x1280) only happens via admin upload. For flat file imports, resize images beforehand using ImageMagick:

```bash
# Resize all images in media/ to max 1980x1280 (preserves aspect ratio, only shrinks)
find content/media/ -type f \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" -o -iname "*.webp" \) \
  -exec mogrify -resize '1980x1280>' {} \;
```

> **Tip:** The `>` flag means "only shrink larger images, never enlarge smaller ones".