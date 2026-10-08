---
title: 'AI Content Index'
h1: AI Content Index
publishedAt: '2026-04-25 12:00'
toc: true
---

Generate CSV indexes of your pages and media for AI tools and content discovery.

## Usage

```bash
php bin/console pw:ai-index [host] [exportDir]
```

- `host` — optional, the site whose pages are exported. Omit it, or pass `""`, to export every site.
- `exportDir` — optional. Defaults to the selected site's flat-file directory (the default site if `host` is omitted), or a new `var/export/<unique-id>/` directory.

## Output

### pages.csv

| Column | Description |
|--------|-------------|
| slug | Page URL slug |
| title | HTML title (falls back to h1) |
| createdAt | Creation date (Y-m-d H:i:s) |
| tags | Comma-separated tags |
| summary | Search excerpt |
| mediaUsed | Media filenames the page references (body, main image, custom property) |
| parentPage | Parent page slug |
| pageLinked | Other page slugs found as substrings in mainContent |
| length | Byte length of mainContent |
| host | Site the page belongs to |

### medias.csv

| Column | Description |
|--------|-------------|
| media | Filename |
| mimeType | MIME type |
| name | Alt text |
| usedInPages | Page slugs referencing this media |

## Example

```bash
# Export pages for a specific host
php bin/console pw:ai-index altimood.com

# Export every site to a custom directory
php bin/console pw:ai-index "" /tmp/pushword-export
```

Both usage columns are read from the stored media↔page relation — see
[Media usage](/media-usage). A site that has never run `pw:media:usage:rebuild`
exports them empty.

The CSV files use comma delimiters and double-quote enclosure.
