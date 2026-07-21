---
title: 'Repurpose: turn a page into a social carousel'
h1: Repurpose
publishedAt: '2026-07-21 12:00'
parentPage: extensions
toc: true
---

Turn any Pushword page into ready-to-post social carousels (LinkedIn, Instagram,
Facebook, Pinterest, Threads). A carousel is a small JSON **spec**; the package
renders it to **self-contained SVG slides** server-side — font and image embedded
as `data:` URIs — so a slide is one portable file that rasterises to a pixel-exact
PNG in any browser.

Because the text is laid out in PHP, **overflow and bad crops become validation
errors before anything is rendered** — the property that makes carousels safe to
author by an AI agent.

## Install

```bash
composer require pushword/repurpose
```

It requires `ext-gd` (text measurement) and `ext-zip` (export). Imagick is **not**
required — the export PDF is written in pure PHP.

## How it fits together

```
Page ──► CarouselDrafter ──► SocialPost ◄──► social-post/{page}/{network}.json
                                  ▲
                                  └── admin studio / REST API / AI agent
                                     │
                                     ▼
                        SlideRenderer + TextLayout (PHP)
                                     │
                                     ▼
                   self-contained SVG  →  PNG (browser)  →  .zip + .pdf
```

- **Spec** — a JSON document (slides, text, focal-point crop, palette, font
  pairing, background effect, status). The `Carousel` model + Symfony Validator are
  the single source of truth, shared by the renderer, the CLI lint and the API.
- **Storage** — a `SocialPost` row (so the admin can list "everything planned this
  week", filter by status, enforce per-network uniqueness) that also round-trips as
  a flat JSON file through `pw:flat:sync`. Works on a DB-first site and a flat-file
  site alike.
- **Studio** — an admin page (`/admin/repurpose`) that previews the deck and
  exports it.

## Author with an agent (recommended)

There is no LLM inside the package. An agent writes the spec against a published
schema and validates it — free, with whatever model you already use.

1. `GET /api/repurpose/schema` — the JSON shape (keys, enums, ranges).
2. `GET /api/repurpose/networks` — formats (ratio/pixels), per-network **hard
   limits** (errors) vs **guidance** (advice), and font pairings.
3. `POST /api/repurpose/validate` — validate a spec, get precise
   `{path, message}` violations (overflow, out-of-bounds crop, disallowed format…).
4. `PUT /api/repurpose/{host}/{network}/{page}` — save it.
5. `GET /api/repurpose/{id}/slide-{n}.svg` — render a slide to check framing.

The [`pw` ai-skill](https://github.com/Pushword/Pushword/tree/main/packages/ai-skills)
ships a `carousel` playbook for exactly this loop.

## Command line

```bash
bin/console pw:repurpose:schema                 # print the JSON Schema
bin/console pw:repurpose:validate spec.json     # lint a spec (precise violations, non-zero exit)
bin/console pw:repurpose:render spec.json out/  # render to out/slide-1.svg …
```

All emit compact JSON when run by an AI agent (`--format=agent`).

## Cropping

The crop is three numbers on a slide's `image`: `focusX`/`focusY` (0..1 focal
point in the source) and `zoom` (≥ 1). It is applied at **render time** as an SVG
clip — never baked into a cached file — so the same numbers stay correct when the
slide is re-rendered at another format's ratio. A landscape photo in a portrait
slide is simply a wide image the frame clips.

## Fonts

Slides use bundled TTF fonts (Apache-2.0 Roboto by default). Prettier pairings —
all Google Fonts — are fetched on demand into your app so the slides can match
your site's typography. Fonts must be local files: `imagettfbbox` measures a file
on disk, and an SVG rasterised for canvas export cannot fetch remote resources.

## Export

The studio rasterises each self-contained SVG to a PNG in the browser and posts
them back; the server assembles a `.zip` (PNGs + `caption.txt`) plus, for LinkedIn,
a multipage `carousel.pdf` — written in pure PHP, no Imagick, so it works on shared
hosting and the FrankenPHP static binary.

## Networks and formats

Platform specs drift, so formats are editable data with a provenance note, and the
validator enforces only **hard limits** (LinkedIn ≤300 pages / ≤100 MB / all pages
one size; Instagram ≤20 slides), never engagement opinions. Fetch the live table
from `GET /api/repurpose/networks`.
