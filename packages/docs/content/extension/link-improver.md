---
title: 'Automatic internal linking for Pushword — Link Improver'
h1: 'Link Improver'
publishedAt: '2026-08-04 12:00'
toc: true
---

Automatic internal linking: the first mention of another page's **name** in your
rendered content becomes a link to that page. Nothing is written into your source
Markdown — disable the option and every auto link is gone.

## Install

```shell
composer require pushword/link-improver
```

The bundle registers itself in `config/bundles.php`, after the core bundle.

Then opt in per app — the filter is wired into every app's `main_content` chain
(right after `Markdown`) but stays inert until you enable it, because it
rewrites content:

```yaml
pushword:
  apps:
    - hosts: [example.tld]
      link_improver: true
      # link_improver_max_links: 0.02     # the default
      # link_improver_ignored_urls: ['/'] # never a target; empty by default
```

## The keyword map is your content

There is no CSV or admin screen to maintain: **each line of a page's `name`
field is a keyword that links to it**. The first line is the name Pushword
displays (breadcrumb, listings); further lines are never displayed, which makes
them pure linking variants — including `*` wildcards, which match up to 10
characters:

```yaml
name: |
  Tour du Mont-Blanc
  TMB en 11 étapes
  tour du Mont*Blanc
```

Commas separate keywords too, as they do in the underlying library's CSV format.
Keyword matching is case-insensitive; the inserted anchor keeps the casing found
in the content. Only published pages of the same host **and locale** are offered
as targets, redirections excluded, and a page never links itself.

Choose names accordingly: a page named `Blog` or `Contact` will attract links
from everywhere its name is written.

`link_improver_ignored_urls` drops pages from the map without touching their
name, which keeps working everywhere else (breadcrumb, listings). Write the URLs
as the report prints them — `/` for the homepage, `/slug` otherwise:

```yaml
link_improver_ignored_urls: ['/', '/contact']
```

The homepage is the usual candidate: its name is often the brand, written on
nearly every page. On a real site, that alone took 44% of the inserted links.
Whether that is what you want is an editorial call — a branded anchor next to
the header link is fine for many sites; ignoring it spends the budget on
topical targets instead.

## What it will not do

The insertion engine refuses to place a link:

- inside a tag, a heading, code (inline or block) — only inside `p`, `span`,
  `b`, `strong`, `em`, `i`, `li`;
- immediately after another link;
- to a target the content already links (with or without `#fragment`,
  `?query` or the absolute `base_url` form);
- beyond the cap: `link_improver_max_links` bounds the **total** of in-content
  links, existing ones included. Below `1` it is a ratio of the word count —
  the default `0.02` means one link per 50 words; `1` or more is an absolute
  count.

## Choosing the cap

Because the cap counts the links already in the content, it reads as a target
density rather than as a number of links to add: a page already linking above
it gets nothing, a neglected page is filled up to it.

The default `0.02` — one link every 50 words — comes from measuring what the
number means in practice. In Wikipedia's running prose (the `<p>` of 30 fr/en
articles, 185 000 words, links to other articles only) the median article sits
at **one link per 20 words**. Wikipedia is the densest reasonable reference, so
half that density is a comfortable ceiling for a content site.

For scale, the same measure on real Pushword sites: a hand-linked site reaches
one link per 48 words, while larger corpora sit between one per 180 and one per
720, with 8 to 49% of pages holding no internal link at all. Those are the pages
the improver is for; `0.01` would have throttled it on exactly the short pages
where a single link matters.

## Auditing what was linked

Automatic linking earns its bad reputation when it is invisible. Four surfaces,
from the page you are reading to the whole site:

- **On the page, logged in as an editor**: every auto link gets an indigo tint
  behind it and a title saying it was inserted, not written. Only a `ROLE_EDITOR`
  with a session sees this — the public HTML and the shared render cache are
  untouched, and an annotated response is sent `private, no-store`. The marking
  is a background, not a change to how your theme underlines links: a theme
  styles its links as it likes (the stock one uses a `border-bottom` and sets
  `text-decoration: none`), so a decoration-based marking would be invisible on
  some sites and doubled on others. Nothing is inserted into the text and no
  word moves, so the page still reads exactly as a visitor sees it.
- **Per page, in the admin**: the *Auto links* button on the page edit screen
  opens a panel listing what this page gained (anchor → target), the cap it was
  measured against, and the keywords its own name offers to other pages. It
  renders that one page on request — this is the surface to open when a page
  gained nothing and you want to know why.
- **In the HTML**: every inserted link carries a bare `data-auto-link`
  attribute — auto links stay distinguishable from editorial links to your
  crawler and to your own eyes (`grep data-auto-link` on a static export).
- **Site-wide**: `php bin/console pw:link-improver` renders every published page
  and reports each inserted link (`page`, anchor, target). `--host example.tld`
  narrows to one app, `--simulate` renders **as if `link_improver: true` were
  set** to preview a site before opting in, and `--format` follows the
  [agent-output](/agent-output) convention.

The panel deliberately does not list which pages link *to* the one you are
looking at: that answer needs the rest of the host rendered, and
[page-scanner](/extension/page-scanner)'s link graph (`pw:link:graph`) already
reports inbound counts, depth and orphans — auto links included, since the scan
renders each page.

## Rendering and cache

Links are decorated at render time, after the Markdown/Twig pass and before the
`Html*` link post-processors — an auto link to a page that becomes unpublished
degrades exactly like an editorial one. Rendering is byte-deterministic: the
map is derived from the pages ordered by slug, longest keyword first, so two
renders of the same page produce the same bytes.

A page's rendered output now depends on the other pages' names: renaming,
publishing or deleting a page already bumps the host's render epoch (`name`,
`slug` and `publishedAt` are listing-relevant fields), so static and page-cache
sites regenerate what a name change can affect.

## Interplay with pages_list

Links inserted by the improver are registered with the link collector, so a
`pages_list(…, excludeAlreadyLinked: true)` rendered after the content — in the
template — will not offer a page the improver just linked in the body. A
`pages_list` written inside the content renders before the improver and cannot
know about them.
