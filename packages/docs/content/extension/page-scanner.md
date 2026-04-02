---
title: 'Page Scanner - Find broken links and content issues'
h1: Page Scanner
publishedAt: '2025-12-21 21:55'
toc: true
---

Find dead links, 404, 301 and more in your content (command line or admin).

## Install

```shell
composer require pushword/page-scanner
```

## Usage

### Command line

```shell
php bin/console pw:page-scan              # scan all hosts
php bin/console pw:page-scan localhost.dev # scan a specific host
php bin/console pw:page-scan --skip-external  # skip external URL checks
php bin/console pw:page-scan --limit=100      # stop after 100 errors
```

### Admin

The scanner is accessible via the admin menu. Results are cached and refreshed automatically.

## What it checks

- **Internal links**: page exists, is published, is not a redirect
- **External links**: HTTP status codes (parallel checking with caching)
- **Anchor links**: target element exists in the page
- **Media files**: referenced images and files exist
- **Parent pages**: parent-child host consistency
- **TODO comments**: deferred actions tied to page publication (see below)

## TODO comments

When writing a page, you can leave TODO comments to remind yourself of actions to take when another page gets published.

### Link when published

Use `<!--TODO:linkWhenPublished slug -->` where you want a link to appear once the target page is published:

```markdown
Read more about this topic
<!--TODO:linkWhenPublished my-upcoming-article -->
in a future article.
```

You can include the intended anchor text:

```markdown
<!--TODO:linkWhenPublished my-upcoming-article "read our detailed guide" -->
```

### Action when published

Use `<!--TODO:doWhenPublished slug "instruction" -->` for generic actions:

```markdown
<!--TODO:doWhenPublished product-launch "add comparison table here" -->
```

### Multi-host support

By default, the slug is resolved against the current page's host. To reference a page on another host, prefix with the host:

```markdown
<!--TODO:linkWhenPublished other-site.com/target-slug "see also" -->
```

### Scanner behavior

| Target page state | Scanner action |
|---|---|
| Slug not found | Warning: unknown page |
| Exists but not published | Silent (still waiting) |
| Now published | Warning: replace TODO with a link (or follow the instruction) |

## Configuration

```yaml
# config/packages/pushword_page_scanner.yaml
pushword_page_scanner:
  min_interval_between_scan: 'PT5M'        # minimum interval between scans
  external_url_cache_ttl: 86400            # external URL cache TTL in seconds (24h)
  parallel_batch_size: 50                  # URLs checked in parallel per batch
  url_check_timeout_ms: 10000             # timeout per external URL check (ms)
  skip_external_url_check: false           # skip external URL validation
  links_to_ignore:                         # glob patterns for links to skip
    - 'https://www.example.tld/*'
    - '/admin/*'
  errors_to_ignore: []                     # error message patterns to suppress
```

`errors_to_ignore` supports global patterns (`"message"`) and per-route patterns (`"host/slug: message"`) with `fnmatch` wildcards.
