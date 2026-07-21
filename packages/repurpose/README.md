# Pushword Repurpose

Turn a [Pushword](https://pushword.piedweb.com) page into ready-to-post social
carousels (LinkedIn, Instagram, Facebook, Pinterest, …).

- **Agent-authored JSON spec** — a carousel is a small JSON document (slides,
  text, focal-point crop, palette, font pairing, background effect, status). An AI
  agent writes it against a published JSON Schema; a human nudges it in the admin.
- **Self-contained SVG slides** — each slide renders server-side to one portable
  SVG with its font and image embedded as `data:` URIs. Text is laid out in PHP,
  so **overflow and bad crops become validation errors before anything is
  rendered**.
- **Pixel-exact export** — the browser rasterises the SVG to PNG; PHP assembles a
  `.zip` (PNGs + caption) and, for LinkedIn, a multipage PDF. No headless browser,
  no npm dependency.
- **Flat or DB** — carousels round-trip through `pw:flat:sync` as JSON files, so
  they live in git on a flat-file site and in the database on a dynamic one.

## Installation

```shell
composer require pushword/repurpose
```

## Status

Work in progress. See [the plan](https://pushword.piedweb.com/extension/repurpose).
