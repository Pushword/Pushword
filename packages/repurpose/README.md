# Pushword Repurpose

Turn a [Pushword](https://pushword.piedweb.com) page into ready-to-post social
posts and carousels (LinkedIn, Instagram, Facebook, Pinterest, …). Pinterest is
modelled as an organic image Pin: one 2:3 visual with a required caption.

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
- **Motion design** — `pw:repurpose:motion` exports a local HyperFrames project:
  editable timeline, SVG layers, crossfades and per-layer animation presets. An
  agent can add custom HTML/JavaScript scenes, preview frames and render an H.264 video.
  Node/HyperFrames dependencies are installed only in the exported project.
- **Flat or DB** — carousels round-trip through `pw:flat:sync` as JSON files, so
  they live in git on a flat-file site and in the database on a dynamic one.

## Installation

```shell
composer require pushword/repurpose
```

## Documentation

Visit [pushword.piedweb.com/extension/repurpose](https://pushword.piedweb.com/extension/repurpose).

For agent-authored animation, use the `pw motion` playbook from Pushword's
[AI skills](https://github.com/Pushword/Pushword/tree/main/packages/ai-skills).
It covers the brief, scene authoring, visual checks, video render, upload and
attachment to an admin draft.

Try the complete [NORD. motion example](examples/nord/README.md), shipped with
this package. From a Composer site's root (Node.js 22+ and FFmpeg):

```sh
node vendor/pushword/repurpose/examples/nord/prepare.mjs /tmp/nord-motion
cd /tmp/nord-motion
npm install
npm run check
npm run still -- --frame=205
npm run render
```

The output directory must be new. Preparation needs no PHP or fixture database.
In the monorepo, use `packages/repurpose` instead of `vendor/pushword/repurpose`.
