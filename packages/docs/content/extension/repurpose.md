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

Installed before seeding a fresh site, the demo fixtures add a ten-slide carousel of
the `examples` page, one feature per slide. Its spec, `src/DataFixtures/Carousel.json`,
is the reference to copy from; the post sits under **Social carousels**. For motion, see
the [NORD. example](#packaged-example-nord) below.

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

## In the admin

From a page without carousels, choose **Repurpose** to create a draft. Existing
carousels appear in the page editor's sidebar with their network and status;
each opens in the studio. The page list shows their count, opening the studio
for one carousel or a list filtered to that page and host for several.

**Social carousels** groups each cover, title, network and source in one column.
Choose **Preview** to enlarge the cover or **Studio** to edit it. The studio's
source card links back to the page editor; standalone carousels have no source.

The Studio alerts you when another user or an agent saves the carousel. If you
have unsaved edits, open the latest version in a new tab to review and reapply
them. The original tab keeps your draft and blocks saves against the old revision.

## Author with an agent

There is no LLM inside the package. An agent writes the spec against a published
schema and validates it — free, with whatever model you already use.

1. `GET /api/repurpose/schema` — the JSON shape (keys, enums, ranges).
2. `GET /api/repurpose/networks` — formats (ratio/pixels), per-network **hard
   limits** (errors) vs **guidance** (advice), and font pairings — each with an
   `installed` flag. Validation through the API downloads a missing pairing.
   Pinterest models an organic image Pin: it requires exactly one 2:3 slide and
   a non-empty caption. Multiple frames belong in a video Pin or paid carousel ad.
3. `POST /api/repurpose/validate` — validate a spec, get precise
   `{path, message}` violations (overflow, out-of-bounds crop, disallowed format…)
   plus non-blocking `warnings` (e.g. text/background contrast below WCAG AA).
4. `PUT /api/repurpose/{host}/{network}/{page}` saves the full spec. The response
   includes the new revision, slide count, warnings and proof URLs
   (`studioUrl`, `previewUrl`, `slideUrls`).
5. `GET /api/repurpose/{id}/preview.png` — the whole deck as one contact-sheet
   PNG, slides sized to the network's **mobile feed width** (`feedMobile` in
   `/api/repurpose/networks`, e.g. ~390px, Pinterest's two-column grid ~186px) so
   text legibility is judged at the realistic worst case. Needs Chromium on the
   host; without it, a 501 points back at the SVGs.
   Or `GET /api/repurpose/{id}/slide-{n}.svg` for one slide.

Before editing an existing carousel, GET the same URL and apply your changes
to its `spec`. Send the returned `ETag` (also available as `revision`) verbatim in
the `If-Match` header on PUT or PATCH, including the quotes. Both methods replace
the full spec. If someone saved in the meantime, the API returns `409` with
`current.spec` and `current_revision`. Review the latest spec, preserve any
intervening edits, then retry with the new revision. Ask the editor which
version to keep when both edits touch the same field. A missing header on an
existing carousel returns `428`. Use a headerless PUT to create a new carousel.
DELETE also requires the current revision.

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
bin/console pw:repurpose:validate spec.json --strict # contrast warnings fail too
bin/console pw:repurpose:render spec.json out/  # render to out/slide-1.svg …
bin/console pw:repurpose:motion spec.json video/ # export an editable HyperFrames project
bin/console pw:repurpose:fonts                  # list font pairings + installed status
bin/console pw:repurpose:fonts bebas-neue-lato  # install a pairing's TTFs (or --all)
bin/console pw:repurpose:fonts --heading=Gabarito --body=Poppins # any Google Fonts families
```

All emit compact JSON when run by an AI agent (`--format=agent`).

## Cropping

A slide's `image` carries `focusX`/`focusY` (0..1 focal point) and `zoom` (≥ 1),
applied at **render time** as an SVG clip, so the same numbers hold when the slide is
re-rendered at another ratio. `media` must name a JPEG, PNG, GIF or WebP of the media
library: anything else is a validation error, since the renderer could only paint it
as a grey placeholder. An image uploaded moments ago, whose derivatives core still
builds in the background, gets its `default` derivative built on first render.

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

`fontPairing` names a registry pairing, or pairs any two Google Fonts families:

```json
"fontPairing": { "heading": "Gabarito", "body": "Poppins" }
```

Headings render at weight 700, body text at 400. Six registry pairings ship with
the package (DM Serif Display+DM Sans, Playfair+Chivo, Montserrat+Work Sans,
Poppins+Inter, Anton+Roboto, Lora+Ubuntu); every other family is downloaded from
Google Fonts into `repurpose.font_dir` (default `var/repurpose/fonts`, outside
`vendor/`), which then caches it: a font on disk is never fetched again.

The studio downloads missing families when it opens a post, previews or saves it, and
so does the API on `validate` and `PUT`; rendering never makes a network call. On the
command line, a font not installed is a validation error naming the command that
installs it: `pw:repurpose:fonts <pairing>` or `pw:repurpose:fonts --heading=… --body=…`.
`var/` is not versioned, so each machine downloads its fonts once. Fonts must be
local: text is measured with `imagettfbbox`, and a rasterised SVG cannot fetch remote
resources.

## Legibility

A slide with an image and no `overlay` gets `0.35` (an explicit `0` is honoured), and
`validate`, `PUT` and the studio preview return non-blocking contrast `warnings` (WCAG
AA large text, 3:1) naming the text field and the fix. Every drawn block is measured:
the tagline in the accent colour, the title and paragraph, and each free text in its
own colour; a highlighted block is measured against its marker. The studio lists them
under the toolbar; `pw:repurpose:validate --strict` turns them into failures.

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
HyperFrames preview and rendering. Music gain follows composition time across loops.
Both the CMS and the motion project use FFmpeg's sidechain compressor; the
project prepares one local soundtrack shared by preview and rendering.

Static slide previews and image exports have no audio. An imported **Rendered
video** keeps its existing soundtrack: export and import a new render to hear
changed audio settings. Previously exported motion projects keep their own files;
export to a new directory to get these audio controls.

## Motion design and agent-authored videos

For guided authoring, visual review and delivery, use **`pw motion [brief or page]`** from the
[`pw` AI skill](https://github.com/Pushword/Pushword/tree/main/packages/ai-skills).
It supports the exported timeline and custom HTML/JavaScript scenes.

`pw:repurpose:motion spec.json video/ --host=example.com` exports a standalone
[HyperFrames](https://github.com/heygen-com/hyperframes) project into a **new** directory. Use
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

Requires Node.js 22+, npm and FFmpeg (including ffprobe) on the rendering machine.
HyperFrames finds or downloads a headless browser; Chromium's system libraries
must be installed. No Node dependency is added to the CMS. The resulting H.264
video is `out/video.mp4`.

HyperFrames uses Apache-2.0, with no company-size threshold or per-render licence
fee for local rendering. The generated project uses native JavaScript animation
and disables HyperFrames telemetry in its npm commands. It needs no cloud account.

`timeline.json` defines dimensions, fps, scene order, durations and incoming
crossfades **in frames**; `timeline.schema.json` describes its shape. Crossfades
overlap scenes and reduce total duration. Each slide exports SVG layers in
drawing order, preserving the static layout and embedded assets. Layers support
`none`, `fade`, `rise` and `zoom-in` animations. Text layers are exported with
`"transition": "sequential"`: they enter after the incoming crossfade and leave
during the first half of the outgoing one, so two slides' texts never overlap
while their images crossfade.

For custom animation, edit `src/scene.mjs` or register an object by slide id in
`src/scenes.mjs`: `html(slide, video)` returns the markup, and
`render(root, frame, slide, video)` updates it for a local frame. Reuse
`slideLayers` or supply your own artwork. Use local assets under `public/`,
referenced as `../public/` from the generated compositions. The exported README
includes a runnable scene example.

`npm run build` generates `index.html`, `compositions/` and the soundtrack under
`public/generated/`; preview, stills and rendering run it automatically. Studio
rebuilds on timeline or scene module changes. Edit the source files: rebuilding
replaces changes made to generated HTML, including browser-editor changes.

`npm run check` reports timeline errors and missing layer assets as JSON. It does
not detect visual overlaps or validate every asset referenced by custom scenes.
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
The player shows the last imported render. After editing scenes in HyperFrames
Studio, render and import the new video to update it.

Agents can attach the same media through the optional top-level `video` field in
the carousel JSON, for example `"video": "campaign-reel.mp4"`. It is a media
filename, not a URL or path. Omit it to detach the video; this does not delete the
media. The studio reports unavailable or unplayable videos without hiding the
static deck.

### Packaged example: NORD.

Every installation of `pushword/repurpose` includes a complete five-scene vertical
reel in `examples/nord/`: custom HTML/JavaScript scenes, a companion static spec, local demo
photos and a preparation script. Its copy is in French, and its spec references
`repurpose-nord.mp4`, `1.jpg` and `3.jpg`: media that only the development app
has. On another site, import the photos and your own render, then use their
filenames, as its README explains. From a Composer site's root:

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
Validate and PUT the updated spec using the revision workflow above, then open its
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
