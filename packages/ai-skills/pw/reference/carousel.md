# carousel — repurpose a page into a social carousel

Turn a Pushword page into ready-to-post social slides (LinkedIn, Instagram,
Facebook, Pinterest, Threads). A carousel is a small JSON **spec**; the package
renders it to self-contained SVG slides server-side, so **text overflow and bad
crops are validation errors you can fix before rendering anything**.

You author the spec; a human nudges it in the studio and exports the PNGs (+ a PDF
for LinkedIn).

## The loop

1. **Read the article.** Fetch the page (its `h1`, body, `mainImage`, and any
   `verticalMainImage`). Pick 5–10 tight, punchy points — one per slide.
2. **Read the project's voice rules first** (see the skill's "Bring the project's
   own rules"). Write copy in that voice, not a generic one.
3. **Fetch the facts the package owns**, in one call:
   - `GET /api/repurpose/schema` — the exact JSON shape (keys, enums, ranges).
   - `GET /api/repurpose/networks` — formats (id → ratio/pixels), per-network
     **hard limits** (errors) vs **guidance** (advice), and available font pairings.
   Or on a flat-file site: `bin/console pw:repurpose:schema`.
4. **Write the spec** (shape below). Keep titles short — the renderer shrinks text
   to fit, but a wall of text still reads badly.
5. **Validate before rendering** — this is the point of the tool:
   - API: `POST /api/repurpose/validate` (body = the spec) → `{valid:true}` or
     `422 {violations:[{path,message}]}`.
   - CLI: `bin/console pw:repurpose:validate spec.json` (or `-` for stdin).
   Fix every violation. Common ones: `title_overflow` (shorten the title),
   `crop_out_of_bounds` / `source_too_small` (change `focusX/focusY/zoom` or pick a
   bigger image), `format` not allowed for the network, `slides` over the cap.
6. **Save it** (creates a `SocialPost`, one per page+network):
   - API: `PUT /api/repurpose/{host}/{network}/{page}` (body = the spec).
   - Flat site: write `content/{host}/social-post/{page}/{network}.json` and run
     `bin/console pw:flat:sync --mode=import`.
7. **Check your framing without a browser** — the validator's geometry report is
   usually enough. If you want eyes on it: `GET /api/repurpose/{id}/slide-{n}.svg`
   returns a self-contained SVG you can open in a headless Chrome.

## Spec shape (author this)

```json
{
  "page": "blog/my-article",
  "network": "linkedin",
  "format": "linkedin-4-5",
  "status": "draft",
  "fontPairing": "playfair-chivo",
  "palette": { "bg": "#0b1120", "text": "#f8fafc", "accent": "#38bdf8" },
  "counter": { "style": "dots", "align": "right" },
  "creator": "lorene",
  "caption": "The post caption goes here.",
  "hashtags": ["pushword", "carousel"],
  "slides": [
    {
      "layout": "bottom", "align": "left",
      "tagline": "Hook", "title": "The one-line promise", "paragraph": "One tight sentence.",
      "swipe": true, "overlay": 0.45, "background": "blobs",
      "image": { "media": "photo.jpg", "focusX": 0.5, "focusY": 0.35, "zoom": 1.1 }
    }
  ]
}
```

Key rules (the schema is the source of truth — always fetch it):

- **`format` must be allowed for `network`** (see `/api/repurpose/networks`).
- **Cropping is `focusX`/`focusY`/`zoom`** — 0..1 focal point + zoom ≥ 1. The same
  numbers stay correct at any format, so don't hardcode pixel crops.
- **Colours/fonts are optional** — omit them to inherit the site's own tokens.
- **`creator`** is a key from the site's `repurpose_creators` config; omit it to
  fall back to the brand. Do not invent creators.
- **Overlay only over an image**; a slide must have at least one text field or an
  image.

## Do not

- Do not carry your own brand voice — read the project's rules.
- Do not skip validation. The whole value is catching overflow/crop errors before
  a single pixel is rendered.
- Do not invent format ids, network names, or font pairings — enumerate them from
  `/api/repurpose/networks`.

## Detect the project shape

- **Monorepo** (`packages/repurpose/`): run `bin/console` from `packages/skeleton/`.
- **Downstream site** (`vendor/pushword/repurpose/`): run `bin/console` from the
  app root; if `pushword/api` is installed, prefer the API, otherwise write the
  flat `.json` and `pw:flat:sync`.
