---
title: 'Pushword Flat File CMS - Markdown and Twig Ready'
h1: Flat
id: 32
publishedAt: '2025-12-21 21:55'
parentPage: extensions
toc: true
---

Transform Pushword in a FlatFile CMS.

## Install

```
composer require pushword/flat-file
```

## Configure (if needed)

Globally under `pushword_static_generator` (in `config/packages`).

Or for _multi-sites_ in `config/package/pushword.yaml`.

```yaml
...:
  flat_content_dir: content #default value
```

## Usage

### Sync with DB (import / export)

```
# Sync : automatically detects whether to import (files newer than DB) or export (DB newer or no files).
php bin/console pw:flat:sync [host]

# imports flat files into the database
php bin/console pw:flat:import [host]

# exports database content to flat files
php bin/console pw:flat:export [host] [exportDir] [--force]
```

Where:

- `host` is facultative (uses default app if not provided)
- `exportDir` (for export only) is facultative (uses `flat_content_dir` by default)
- `--force` (for export only) forces overwriting even if files are newer than DB

## Generate AI index

```
php bin/console pw:ai-index [host] [exportDir]
```

Generate two CSV files (`pages.csv` and `medias.csv`) with metadata useful for AI tools.

Where:

- `host` is facultative (uses default app if not provided)
- `exportDir` is facultative (uses `flat_content_dir` by default)

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