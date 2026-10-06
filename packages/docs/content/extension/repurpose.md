---
title: 'Repurpose: turn a page into a social carousel'
h1: Repurpose
publishedAt: '2026-07-21 12:00'
parentPage: extensions
toc: true
---

Turns a page into social posts and carousels (LinkedIn, Instagram, Facebook, Pinterest,
Threads). A post is a JSON **spec** rendered server-side to **self-contained SVG
slides** (font and images embedded as `data:` URIs), which rasterise to pixel-exact
PNGs in any browser. Text is laid out in PHP, so **overflow and bad crops are
validation errors before anything renders** — which makes specs safe for an AI agent
to write.

## Install

```bash
composer require pushword/repurpose
```

Requires `ext-gd` (text measurement) and `ext-zip` (export); no Imagick.

Installed before seeding a fresh site, the demo fixtures add a ten-slide carousel of
the `examples` page, one feature per slide. Its spec, `src/DataFixtures/Carousel.json`,
is the reference to copy from; the post sits under **Social posts**.

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
- **Studio** — an admin page (`/admin/repurpose/studio/{id}`) previewing the deck at
  the network's mobile feed width (zoom slider or Ctrl+scroll; always reopens at
  100%) and exporting it. Click a text to edit it in place; drag any text on the
  slide (a stack field becomes a free text box where it lands).

## Author with an agent (recommended)

There is no LLM inside the package. An agent writes the spec against a published
schema and validates it — free, with whatever model you already use.

1. `GET /api/repurpose/schema` — the JSON shape (keys, enums, ranges).
2. `GET /api/repurpose/networks` — formats (ratio/pixels), per-network **hard
   limits** (errors) vs **guidance** (advice), and font pairings — each with an
   `installed` flag; only pick installed ones (the rest fall back to Roboto).
   Pinterest models an organic image Pin: it requires exactly one 2:3 slide and
   a non-empty caption. Multiple frames belong in a video Pin or paid carousel ad.
3. `POST /api/repurpose/validate` — validate a spec, get precise
   `{path, message}` violations (overflow, out-of-bounds crop, disallowed format…)
   plus non-blocking `warnings` (e.g. text/background contrast below WCAG AA).
4. `PUT /api/repurpose/{host}/{network}/{page}` — save it. The response echoes the
   persisted slide count, the warnings, and `studioUrl` / `previewUrl` /
   `slideUrls` so the result can be confirmed and eyeballed without a follow-up GET.
5. `GET /api/repurpose/{id}/preview.png` — the whole deck as one contact-sheet
   PNG, slides sized to the network's **mobile feed width** (`feedMobile` in
   `/api/repurpose/networks`, e.g. ~390px, Pinterest's two-column grid ~186px) so
   text legibility is judged at the realistic worst case. Needs Chromium on the
   host; without it, a 501 points back at the SVGs.
   Or `GET /api/repurpose/{id}/slide-{n}.svg` for one slide.

The [`pw` ai-skill](https://github.com/Pushword/Pushword/tree/main/packages/ai-skills)
ships a `carousel` playbook for exactly this loop.

## Command line

```bash
bin/console pw:repurpose:schema                 # print the JSON Schema
bin/console pw:repurpose:validate spec.json     # lint a spec (precise violations, non-zero exit)
bin/console pw:repurpose:render spec.json out/  # render to out/slide-1.svg …
bin/console pw:repurpose:fonts                  # list font pairings + installed status
bin/console pw:repurpose:fonts bebas-neue-lato  # install a pairing's TTFs (or --all)
```

All emit compact JSON when run by an AI agent (`--format=agent`).

## Cropping

A slide's `image` carries `focusX`/`focusY` (0..1 focal point) and `zoom` (≥ 1),
applied at **render time** as an SVG clip, so the same numbers hold when the slide is
re-rendered at another ratio.

## Text

The layout stack (`tagline`/`title`/`paragraph`) is anchored by `layout` × `align`
and auto-fits its size. An explicit `\n` in any field makes a hard line break.
For text outside the stack — annotations, callouts — a slide takes free text
boxes in `texts[]`: `content`, a fractional box (`x`, `y`, `width`, with
`x + width ≤ 1`), `size` (fraction of the frame width), `align`, `font`
(`body`/`heading`) and an optional `color`. Fractions survive a format retarget; a box
that would overflow the slide bottom shrinks to fit.

A `highlight` colour (on a slide, behind each title line, or on a free text box) paints
a rounded marker sized from the measured line widths.

In the studio, dragging a stack field or clicking "→ free text" converts it into a free
text box with its rendered size, wrap width, position and colours (a tagline's
uppercase and a title's highlight included). The field empties; the box no longer
follows the slide's layout, align or text-scale controls.

## Fonts

Six pairings ship with the package (DM Serif Display+DM Sans, Playfair+Chivo,
Montserrat+Work Sans, Poppins+Inter, Anton+Roboto, Lora+Ubuntu); the other ~45 —
all Google Fonts — are installed on demand with `pw:repurpose:fonts <pairing>`
into `repurpose.font_dir` (default `var/repurpose/fonts`, outside `vendor/`). A pairing
not installed falls back to Roboto — check the `installed` flag in
`GET /api/repurpose/networks` or `pw:repurpose:fonts`. Fonts must be local: text is
measured with `imagettfbbox`, and a rasterised SVG cannot fetch remote resources.

## Legibility

A slide with an image and no `overlay` gets `0.35` (an explicit `0` is honoured), and
`validate`, `PUT` and the studio preview return non-blocking contrast `warnings` (WCAG
AA large text, 3:1) naming the slide and the fix.

## Creator byline

Slides can carry a byline (avatar + name + role, top-left; `creatorOnSlides`
picks which slides, `creatorOrientation` puts the text beside the avatar or
stacked under it). `creator` is either a key from the site's
`repurpose_creators` config:

```yaml
pushword:
  apps:
    - hosts: [example.com]
      repurpose_creators:
        jane:
          name: 'Jane Doe'
          role: 'Editor in chief'
          avatar: 'jane.jpg' # a media file name; optional
```

…or an inline `{name, role?, avatar?, type?}` object. Omitted or unknown, the brand
(site name) signs; an unknown key also raises a warning listing the known ones. A
missing avatar renders an initials disc. To source creators elsewhere, bind your own
`Pushword\Repurpose\Service\CreatorResolverInterface` (`resolve()`, plus
`available()` feeding the studio dropdown and the warning).

## Export

The studio rasterises each SVG to PNG in the browser and posts them back; the server
assembles a `.zip` (PNGs + `caption.txt`) plus, for LinkedIn, a multipage
`carousel.pdf` written in pure PHP. With `ffmpeg` on the host, the same frames also
export as an `.mp4` slideshow.

Binaries are auto-detected from `PATH`; override with `repurpose.chromium_binary`
(preview contact sheet) and `repurpose.ffmpeg_binary` (video).

## Networks and formats

Formats are data with a provenance note; the validator enforces only **hard limits** (LinkedIn ≤300 pages / ≤100 MB / all pages
one size; Instagram ≤20 slides; Pinterest = one organic image Pin with a caption),
never engagement opinions. Caption limits include the hashtags appended at export.
Fetch the live table from `GET /api/repurpose/networks`.
