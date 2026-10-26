---
title: 'Repurpose: social carousels and animated videos'
h1: Repurpose
publishedAt: '2026-07-21 12:00'
parentPage: extensions
toc: true
---

Turn a page into social posts, carousels and animated videos for LinkedIn,
Instagram, Facebook, Pinterest, Threads, YouTube Shorts, TikTok and X. A JSON
**spec** renders to self-contained SVG slides with embedded fonts and images. PHP
validates text overflow and crop bounds before rendering; the browser exports PNGs.

## Install

```bash
composer require pushword/repurpose
```

Requires `ext-gd` (text measurement) and `ext-zip` (export); no Imagick.

Demo fixtures add sample posts under **Social posts**. For a portable starting
spec, use the package's `examples/nord/spec.json`, described below.

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

## Author with an agent

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

## YouTube Shorts, TikTok and X

Choose **YouTube Shorts** (`youtube`) or **TikTok** (`tiktok`) in the studio's
network selector to create a separate vertical version. Both use `story-9-16`
(1080 × 1920) and advertise `video` as their export kind in the networks API.
Use **Slideshow .mp4** to render the slides, or attach an existing MP4/WebM from
the media library. Switching networks preserves the attached video; changing the
slide format does not resize or regenerate that file. Upload the finished video
through the platform's own publishing interface and check its duration and caption
limits there ([YouTube Shorts upload rules](https://support.google.com/youtube/answer/12779649),
[TikTok publishing reference](https://developers.tiktok.com/doc/content-posting-api-reference-direct-post)).

**X** (`x`) offers `x-16-9` (1600 × 900) and `x-1-1` (1080 × 1080) image exports.
A post accepts up to four images; drafts keep a cover, up to two body slides and
a closing slide. Switching an existing carousel to X keeps its first four slides.
Caption length needs review in X's composer because its
[weighted character count](https://docs.x.com/fundamentals/counting-characters)
handles URLs and emoji differently; Repurpose does not validate that count.

## Command line

```bash
bin/console pw:repurpose:schema                 # print the JSON Schema
bin/console pw:repurpose:validate spec.json     # lint a spec (precise violations, non-zero exit)
bin/console pw:repurpose:render spec.json out/  # render to out/slide-1.svg …
bin/console pw:repurpose:motion spec.json video/ # export an editable Remotion project
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

## Voice and music

In the studio, open **Voice and music**, above the deck settings. Choose or upload
a voice recording and a music file independently (MP3, WAV, M4A, OGG or FLAC). Each
track has a volume from 0 to 100% (0 mutes it), plus fade-in and fade-out durations in seconds (0–30).
Both start at the beginning of the video. Narration plays once; music loops to the
video end. Fade-out ends at the voice recording's end or the video end, whichever
comes first; music fades at the video end.

**Lower music automatically during narration** is enabled by default. It follows
the voice signal, lowers music while narration is audible, and restores it
smoothly during pauses and after narration ends. Muting voice disables its effect.

The optional carousel `audio` field stores these settings for the API and flat files:

```json
"audio": {
  "voice": {"media": "narration.mp3", "volume": 1, "fadeIn": 0.2, "fadeOut": 0.5},
  "music": {"media": "music.mp3", "volume": 0.3, "fadeIn": 1, "fadeOut": 2},
  "ducking": true
}
```

Omit a track to remove it. The files must exist in the site's media library.
**Slideshow .mp4** includes the current editor's audio settings, even before saving.
A motion project export copies both source files into `public/audio/`, preserves
separate tracks in `timeline.json` (`src` replaces `media`), and applies the mix in
Remotion preview and rendering. Music gain follows composition time across loops.
The CMS uses FFmpeg's sidechain compressor; Remotion computes a deterministic
frame-level envelope from the voice waveform.

Static slide previews and image exports have no audio. An imported **Rendered
video** keeps its existing soundtrack: export and import a new render to hear
changed audio settings. Previously exported motion projects keep their own files;
export to a new directory to get these audio controls.

## Motion design and agent-authored videos

For guided authoring, visual review and delivery, use **`pw motion [brief or page]`** from the
[`pw` AI skill](https://github.com/Pushword/Pushword/tree/main/packages/ai-skills).
It supports the exported timeline and custom React scenes.

`pw:repurpose:motion spec.json video/ --host=example.com` exports a standalone
[Remotion](https://www.remotion.dev/docs) project into a **new** directory. Use
`instagram` / `story-9-16` for a vertical video, or another valid network/format
pair for square or landscape output. The command validates the carousel before
writing and resolves the creator using the selected site.

```bash
cd video
npm install
npm run check
npm run studio
npm run still -- --frame=45
npm run render
```

Requires Node.js 22+ and npm on the rendering machine. Remotion installs its own
rendering dependencies, including a headless browser, and has separate
[license terms](https://www.remotion.dev/docs/license/pricing). No Node dependency
is added to the CMS. The resulting H.264 video is `out/video.mp4`.

`timeline.json` defines dimensions, fps, scene order, durations and incoming
crossfades **in frames**; `timeline.schema.json` describes its shape. Crossfades
overlap scenes and reduce total duration. Each slide exports SVG layers in
drawing order, preserving the static layout and embedded assets. Layers support
`none`, `fade`, `rise` and `zoom-in` animations.

For custom animation, edit `src/Scene.jsx` or register a React component by slide
id in `src/scenes.jsx`. Reuse `SlideLayers` or supply your own artwork and local
assets. Derive motion from `useCurrentFrame()` so seeking and rendering agree.
The exported README includes a custom scene example.

`npm run check` reports timeline errors and missing layer assets as JSON. It does
not detect visual overlaps or validate every asset referenced by custom JSX.
Inspect stills at mobile size for entrances, text crossings, transitions and the
final frame, then render and review the MP4. Timeline edits do not update
`spec.json` or the stored post. Keep the editable project outside `vendor/`;
exporting into an existing directory is refused. The admin's **Slideshow .mp4**
button exports fixed images; render custom motion from the exported project.

### Watch the rendered video in the admin

Import the MP4 or WebM into the media library, then select it under **Deck →
Rendered video** in the studio. The **Slides / Rendered video** toggle switches
between the live static deck and a native video player with playback, seeking,
fullscreen and download. A post with an attached video opens on the video view.
The player shows the last imported render. After editing scenes in Remotion
Studio, render and import the new video to update it.

Agents can attach the same media through the optional top-level `video` field in
the carousel JSON, for example `"video": "campaign-reel.mp4"`. It is a media
filename, not a URL or path. Omit it to detach the video; this does not delete the
media. The studio reports unavailable or unplayable videos without hiding the
static deck.

### Packaged example: NORD.

Every installation of `pushword/repurpose` includes a complete five-scene vertical
reel in `examples/nord/`: custom React scenes, a companion static spec, local demo
photos and a preparation script. From a Composer site's root:

```sh
node vendor/pushword/repurpose/examples/nord/prepare.mjs /tmp/nord-motion
cd /tmp/nord-motion
npm install
npm run check
npm run still -- --frame=205
npm run render
```

Use a new output directory; in the monorepo replace `vendor/pushword/repurpose`
with `packages/repurpose`. Preparation requires Node.js 22+, but no PHP, fixture
database or running CMS. Read the example's README (copied to `EXAMPLE.md` in
the generated project) for adapting the scenes, importing the companion deck's
images and attaching the MP4. Keep editable projects outside `vendor/`.

For API delivery, upload `out/video.mp4` using
[`POST /api/media/{filename}`](/media-api#multipart-file-upload). Set `video` to
the response's `filename`, which may differ after renaming or deduplication.
Validate and PUT the updated spec through the Repurpose API, then open its
returned `studioUrl`. Saving a draft does not publish to a social network.

The development fixtures reuse this example's spec and include its rendered MP4
at `packages/dev-app/media~/repurpose-nord.mp4`; normal fixture loading needs no
Node.js or rendering tools.

## Networks and formats

Validation checks allowed formats, slide counts and captions. Caps include
LinkedIn's 300 pages, Instagram's 20 slides, Pinterest's single captioned image,
and X's four images. Caption limits include hashtags appended at export.
Publishing guidance does not block validation; file-size limits in the registry
are reference data, so check the export before uploading. Fetch limits, formats
and guidance from `GET /api/repurpose/networks`.
