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
php bin/console pw:link:graph example.com        # another host
php bin/console pw:link:graph --page=about       # one page, with its inbound sources
php bin/console pw:link:graph --orphans          # only orphans, exit code 1 if any
```

A link graph is always scoped to **one host**: a page earns its links from its own
site, and mixing sites in one report answers a question nobody asks. Omitting the
argument therefore means the first configured site, not all of them. The flip side:
an inbound link coming from *another* host is not counted, so a page linked only
from another locale reads as an orphan.

The command never renders anything itself: it reads the snapshot the last scan left
in `var/page-scan-graph--<host>`, and runs `pw:page-scan` synchronously when there is
none. An all-hosts `pw:page-scan` writes one snapshot per host, so scanning
everything then reporting one site never re-renders.

### Staleness

A stale graph is not a graph, so the command rebuilds one rather than report it. Each
snapshot records the corpus it was taken from — how many pages were published, and
when the last one was edited — and reading it compares that against the database. Add,
edit or delete a page and the next `pw:link:graph` re-runs the scan; leave the content
alone and it reports instantly, however old the snapshot is.

It has to work that way round: a page's inbound count changes when **other** pages are
edited, so nothing you can read on a page tells you its own numbers still hold, and
`generatedAt` alone only ever told you the age — never whether it mattered.

Two changes move no timestamp and no count, so they slip through: editing an
unpublished draft (correctly — it is not in the graph), and swapping one page for
another within the same second. `pw:page-scan` refreshes everything either way.

Over the API the rule is inverted, because `GET /api/link-graph` cannot render: it
returns the graph with `stale: true` and a `triggerUrl` to `POST /api/page-scan`,
and leaves the decision to the caller.

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
| a link from or to a `noindex` page | no — see below |

Navigation and footer links **are** counted. They inflate the inbound count of the
few pages every template links to (home, contact, legal), never the ones you are
trying to strengthen, and removing them spreads the distribution without reordering
it.

### What counts as a page

The graph is the **indexable** graph — the corpus `pages_list()` builds by default.
A `noindex` page is scanned like any other (its links still get checked), but it is
kept out of the graph on both sides:

- **As a target**, because it is an orphan by design. A search page, a checkout, a
  guest-post form is not meant to be linked, and a `--orphans` gate that lists them
  is red forever, which makes it useless in CI.
- **As a source**, because its links are not editorial. A single `noindex` search
  page listing 243 of your 263 pages adds +1 to nearly every inbound count — and the
  pages nothing really links to then hide at `in:1` instead of standing out at `in:0`.

Redirections are not nodes either: a 301 is not a page. So `pageCount` is the number
of pages **in the graph**, which is smaller than the number of pages scanned.

### Depth

`depth` is how many clicks a page is from the homepage, breadth-first. It is the one
dimension of internal linking that is not just another way of counting inbound links,
which is why it is computed rather than tallied. `depth: 0` is the homepage,
`null` means unreachable.

Only a page whose slug is exactly `homepage` roots the walk. A locale home
(`fr/homepage`) is a page like any other: it is reached *from* the home. When the host
has no scanned homepage at all, every depth is `null` by absence of a root rather than
by structure — `homepageScanned` says so, to keep the two from being confused.

### Orphans

An orphan is a page with at most one inbound link. A homepage is never an orphan:
it is where visitors land, however few links point back to it. Neither is a `noindex`
page, which is not in the graph at all — the gate only ever asks about pages that are
supposed to be linked.

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

- **Internal links**: page exists, is published, is not a redirect, is not `noindex` (see below)
- **Relative links**: an internal link must start with `/` (see below)
- **External links**: HTTP status codes (parallel checking with caching)
- **Anchor links**: target element exists in the page
- **Media files**: referenced images and files exist
- **Parent pages**: parent-child host consistency
- **TODO comments**: deferred actions tied to page publication (see below)

URLs written inside a `<code>` or `<pre>` block are illustrations, not links, and
are never checked.

## Links to noindex pages

A crawlable link to a `noindex` page spends crawl budget and link equity on a page
that cannot rank. The scanner reports it as:

```
`/search` noindex page: obfuscate this link to keep it out of the crawl.
```

Fix it with `link()`, which renders a `<span data-rot>` instead of an `<a href>`:
visitors still follow it, robots never see it. An already obfuscated link is
therefore silent — it is decrypted and checked like any other, so it is still
validated against the usual 404 and redirect rules, just exempt from this one.

Only the `noindex` case is reported here. An unpublished or redirecting target is
already covered by its own message, so nothing is reported twice.

## Relative links

Pushword serves every page from the root, so `[Quiz](extension/quiz)` resolves
against the current path instead of `/extension/quiz` — usually a 404, sometimes a
silent detour. Nothing else catches it: [the link graph](/extension/page-scanner#link-graph),
`excludeAlreadyLinked` and the checks above all key on a leading slash, so a
relative link is invisible to every internal tool. The scanner reports it as:

```
`extension/quiz` relative link, an internal link must start with /
```

Fix it by writing the target absolute (`/extension/quiz`). Genuinely relative
targets can be silenced per page with `pageScanLinksToIgnore`.

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
