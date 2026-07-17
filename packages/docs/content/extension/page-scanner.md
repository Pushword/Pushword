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

### AI agents

When the command detects it is running inside an AI agent (Claude Code, Cursor,
Gemini CLI, Codex, …, via the same environment variables as `laravel/agent-detector`),
it drops the progress bar, colors, PID, timing and memory lines and emits a single
compact JSON document instead — issues grouped per page, ignored errors filtered out:

```json
{"tool":"pw:page-scan","result":"failed","pages_scanned":42,"pages_with_errors":1,"errors":2,"issues":[{"page":"localhost.dev/about","errors":["Dead link → `/old-page`","Broken anchor → `#missing`"]}],"duration_ms":1280}
```

Detection is automatic; force it either way with `--format=agent` (JSON) or
`--format=text` (human output). The admin UI always uses text.

### Admin

The scanner is accessible via the admin menu. Results are cached and refreshed automatically.

### API

With the [API extension](/extension/api) installed, the same scan is available over REST at
`POST/GET /api/page-scan` (background dispatch + polling) for scripted and agent workflows.

{id=link-graph}
## Link graph

While it renders every page, the scan also records which pages link to which. That
costs nothing extra — the HTML is already in memory — and `pw:link:graph` reports it:

```shell
php bin/console pw:link:graph                    # every page: inbound, outbound, depth
php bin/console pw:link:graph localhost.dev      # one host
php bin/console pw:link:graph --page=about       # one page, with its inbound sources
php bin/console pw:link:graph --orphans          # only orphans, exit code 1 if any
```

The command never renders anything itself: it reads the snapshot the last scan left
in `var/page-scan-graph`, and runs `pw:page-scan` synchronously when there is none.
Every output carries a `generatedAt` — mind it, because a page's inbound count
changes when **other** pages are edited, so a stale graph misleads without anything
on the page itself having moved. Re-run `pw:page-scan` to refresh it.

Like the scan, it emits compact JSON to AI agents and honours `--format`
(see [agent output](/agent-output)). With the [API extension](/extension/api),
`GET /api/link-graph` returns the same report.

### What counts as a link

Only crawlable `<a href>` links, because only those are links a crawler can follow.
Nothing is filtered out on purpose — it falls out of how pages render:

| Rendered as | In the graph |
|---|---|
| `<a href="/slug">` | yes |
| `link('anchor', page)` → `<span data-rot="…">` | no — obfuscated, has no href |
| a link to an unpublished page → `<span data-status="unpublished">` | no — hidden at render time |
| `src`, `data-img`, `data-bg` | no — those are assets, not links |
| links to media, static files or dead slugs | no — the target is not a page |

Navigation and footer links **are** counted. They inflate the inbound count of the
few pages every template links to (home, contact, legal), never the ones you are
trying to strengthen, and removing them spreads the distribution without reordering
it. Redirections are not nodes: a 301 is not a page.

### Depth

`depth` is how many clicks a page is from the homepage, breadth-first. It is the one
dimension of internal linking that is not just another way of counting inbound links,
which is why it is computed rather than tallied. `depth: 0` is the homepage,
`null` means unreachable.

Only a page whose slug is exactly `homepage` roots the walk. A locale home
(`fr/homepage`) is a page like any other: it is reached *from* the home. When a host
has no scanned homepage at all, every depth it reports is `null` by absence rather
than by structure — the report names those hosts in `hostsWithoutHomepage` so the
two do not get confused.

### Orphans

An orphan is a page with at most one inbound link. A homepage is never an orphan:
it is where visitors land, however few links point back to it.

`--orphans` exits non-zero when any remain, so it gates a CI pipeline: every page
should be reachable **without a pager**, as a spare wheel.

### Known limit: pagination

Rendered offline, `pages_list()` only ever emits its first pager page — there is no
request to carry a pager number, so it defaults to 1. The graph is therefore the
graph a crawler reaches *without paginating*, which is exactly what `orphans` means
here, but it skews the other two metrics:

| Field | Under the partial graph |
|---|---|
| `orphans` | exact — reachable-without-a-pager *is* the rule being enforced |
| `inboundCount` | a **lower bound** — links from pager 2+ are not seen |
| `depth` | an **upper bound** — a page listed only on pager 3 reads as unreachable |

Enumerating pager pages would mean nodes that are URLs rather than pages
(`/blog?page=3` is not an entity), so it is deliberately left out for now.

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
