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

- `host` — optional, filter pages by host. Omit to export all pages.
- `exportDir` — optional, output directory. Defaults to the flat-file content directory for the host, or `var/export/` if flat is not configured.

## Output

### pages.csv

| Column | Description |
|--------|-------------|
| slug | Page URL slug |
| title | HTML title (falls back to h1) |
| createdAt | Creation date (Y-m-d H:i:s) |
| tags | Comma-separated tags |
| summary | Search excerpt |
| mediaUsed | Media filenames referenced in content |
| parentPage | Parent page slug |
| pageLinked | Other page slugs linked from content |
| length | Character count of mainContent |

### medias.csv

| Column | Description |
|--------|-------------|
| media | Filename |
| mimeType | MIME type |
| name | Alt text |
| usedInPages | Page slugs that reference this media |

## Example

```bash
# Export pages for a specific host
php bin/console pw:ai-index altimood.com

# Export all pages to a custom directory
php bin/console pw:ai-index "" /tmp/pushword-export
```

The CSV files use comma delimiters and double-quote enclosure. Feed them to your AI agent for content analysis, link graph extraction, or editorial audits.
