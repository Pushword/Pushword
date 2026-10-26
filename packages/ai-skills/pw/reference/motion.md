# motion — create an animated post or Reel

Deliver an editable motion project, its rendered MP4 and a saved Pushword draft
whose admin studio plays that video. A draft in Pushword is not publication to a
social network. For static slides only, use [carousel.md](carousel.md).

## Locate the tools

| Resource | Monorepo | Composer site |
| --- | --- | --- |
| Console working directory | `packages/dev-app/` | App root |
| Repurpose package | `packages/repurpose/` | `vendor/pushword/repurpose/` |
| Feature docs | `packages/docs/content/extension/repurpose.md` | `vendor/pushword/docs/content/extension/repurpose.md` |
| Media API docs | `packages/docs/content/media-api.md` | `vendor/pushword/docs/content/media-api.md` |

Rendering needs Node.js 22+, npm, FFmpeg (including ffprobe) and Chromium's system libraries. The CMS needs
none of the Node dependencies: install them in the generated project. Read its
README for the pinned runtime and render commands. Keep authored projects and
their npm lockfiles outside `vendor/`; Composer updates replace package files.

## Choose the content and starting point

Read the brief or source article and the site's voice/brand rules. Identify the
network, format, message, assets and intended duration from that context. Fetch
`/api/repurpose/schema` and `/api/repurpose/networks` (or use
`bin/console pw:repurpose:schema` and the local docs). Do not invent network or
format ids. Instagram + `story-9-16` gives a 1080 × 1920 vertical composition.

Use either starting point according to the brief:

- **Animate an existing carousel:** write or retrieve its spec, validate it with
  `bin/console pw:repurpose:validate spec.json`, then export:
  `bin/console pw:repurpose:motion spec.json /tmp/my-reel --host=example.com`.
  The new project contains the static artwork as editable SVG layers.
- **Create custom scenes:** use the same export and edit `src/scenes.mjs`, or
  start from the complete NORD. example shipped in `<repurpose>/examples/nord/`.
  From a Composer site's root:

  ```sh
  node vendor/pushword/repurpose/examples/nord/prepare.mjs /tmp/my-reel
  ```

  In the monorepo use `packages/repurpose` instead. This example needs no PHP or
  fixture database to prepare. Read its README before adapting it: its static
  spec references demo media that must be replaced or imported on another site.

Both commands refuse existing output directories. For revisions, edit the
existing project; do not re-export over it. Choose a new directory if new SVG
artwork is needed, then copy the desired layers deliberately.

## Author the animation

Read the generated README and `timeline.schema.json` before editing. There are
two compatible levels:

- `timeline.json`: order, durations and incoming crossfades in **frames**, plus
  per-layer `none`, `fade`, `rise` or `zoom-in`. Crossfades overlap scenes; they
  reduce total duration. Layer order is back-to-front.
- `src/scenes.mjs`: register objects by timeline scene id (`slide-1`, etc.).
  `html(slide, video)` returns static HTML in Node;
  `render(root, frame, slide, video)` updates that markup in the browser for the
  requested local frame. Reuse `slideLayers` from `./scene.mjs` or supply all
  artwork. NORD. demonstrates five complete HTML/JavaScript scenes.

Derive animation from frame numbers. Timers and random values make seeking and
rendering disagree. Reference local files under `public/` with `../public/` in
scene markup, which is generated under `compositions/`. Keep images and fonts
in the markup so HyperFrames can wait for them. Escape dynamic text with
`escapeHtml` from `scene.mjs` before inserting it into HTML.

`index.html`, `compositions/` and `public/generated/` are generated outputs.
Edit `src/` and `timeline.json`; the npm commands rebuild them, and Studio
watches these sources. Changes made to generated HTML in the HyperFrames browser
editor are replaced on rebuild. Local rendering needs no HeyGen account; the
npm commands disable HyperFrames telemetry.

Add narration and music through the carousel spec's `audio` field before
exporting, not in scene code. `audio.voice` and `audio.music` name audio files in
the site's media library (MP3, WAV, M4A, OGG or FLAC; upload them through the
media API first). The export copies them to `public/audio/`, and `timeline.json`'s
`audio` plays, fades and ducks them across every scene. Do not also add an
`<audio>` for them in a scene: the track would play twice. For a project prepared
from NORD., copy the files under `public/audio/` and declare them in
`timeline.json`'s `audio` (see the generated README).

`spec.json` is the companion static deck and post metadata. Custom scene copy,
`timeline.json` and the spec are independent: keep intended changes in sync.
Changing the format also requires checking custom layouts at the new dimensions.

## Verify what will actually be delivered

Inside the generated project:

```sh
npm install
npm run check
npm run still -- --frame=45
npm run studio
npm run render
```

`studio` is optional and stays running; use a separate terminal for rendering,
or stop it after previewing. Stills and MP4 rendering work without that server.

`check` validates timing, declared SVG layer files and the `audio` settings and
files in `timeline.json`. It does **not** compile
custom scenes, verify all assets referenced by scene markup or JavaScript, detect visual overlaps, or
judge legibility. A passing check alone is not a reviewed video.

Render and inspect representative frames: scene entrances, settled text, moving
elements crossing text, transition overlaps and the final frame. Each `still`
replaces `out/preview.png`, so keep copies when comparing. Frame selection must
follow the actual timeline; NORD.'s README gives its starting frames. Inspect at
mobile size as well as full resolution: text must remain readable and separate
from paths, crops and other text throughout the motion. Fix issues, then render
`out/video.mp4` and inspect the affected frames in that final file. Check its
dimensions and duration against the brief. For narration or music, check the
audio and its timing too: narration does not lengthen the video and is cut at its
last frame.

## Attach the render and save the draft

Use the site's existing authenticated API access (`pushword/api`). The following
commands run from the generated project with `PUSHWORD_URL` (no trailing slash),
`PUSHWORD_TOKEN` and `PUSHWORD_HOST` set for the intended site. The example creates
`standalone/my-reel`; choose the actual key before saving. The key is
`(host, network, page)`, and PUT replaces that post's spec. When editing a post,
retrieve its current spec first and preserve fields outside the requested edit.

1. Upload the MP4 through the media API:

   ```sh
   curl --fail-with-body --silent --show-error \
     -H "Authorization: Bearer $PUSHWORD_TOKEN" \
     -F 'file=@out/video.mp4' -F 'alt=My reel' \
     "$PUSHWORD_URL/api/media/my-reel.mp4" > media.json
   ```

   Use the returned **`filename`**, including when the response says
   `duplicate: true`. Name collisions may produce a suffix; do not assume the
   requested filename was used.

   If upload fails, read the response before retrying. PHP's
   `upload_max_filesize` and `post_max_size` can be smaller than a rendered MP4;
   reduce its encoded size or adjust the site's upload limits as appropriate.
   Do not proceed to saving a video reference for an upload that failed.

2. Set that filename in the companion spec:

   ```sh
   node --input-type=module <<'JS'
   import {readFileSync, writeFileSync} from 'node:fs';
   const spec = JSON.parse(readFileSync('spec.json', 'utf8'));
   const media = JSON.parse(readFileSync('media.json', 'utf8'));
   if (typeof media.filename !== 'string') throw new Error('Upload returned no filename');
   spec.page = 'standalone/my-reel';
   spec.status = 'draft';
   spec.video = media.filename;
   writeFileSync('spec.json', JSON.stringify(spec, null, 2) + '\n');
   JS
   ```

   Replace sample copy, caption and image references before saving a new post.
   Import needed static-deck images through the same media API and use their
   returned filenames. Scene assets remain inside the motion project.

3. Validate the updated spec; inspect and resolve violations and warnings:

   ```sh
   curl --fail-with-body --silent --show-error \
     -H "Authorization: Bearer $PUSHWORD_TOKEN" -H 'Content-Type: application/json' \
     --data-binary @spec.json "$PUSHWORD_URL/api/repurpose/validate"
   ```

4. Save the draft, using the same network and page as the spec:

   ```sh
   NETWORK=$(node -p "require('./spec.json').network")
   curl --fail-with-body --silent --show-error -X PUT \
     -H "Authorization: Bearer $PUSHWORD_TOKEN" -H 'Content-Type: application/json' \
     --data-binary @spec.json \
     "$PUSHWORD_URL/api/repurpose/$PUSHWORD_HOST/$NETWORK/standalone/my-reel" > post.json
   ```

   Confirm the returned slide count and warnings, then GET the same URL and
   verify `spec.video` matches the upload's filename. Open the returned
   `studioUrl` to verify **Rendered video** playback and seeking. `previewUrl`
   and `slideUrls` show the static deck, not the animation. Editing the admin's
   slides does not re-render the video; **Slideshow .mp4** exports static slides.

Without the API, import the MP4 through the admin media library and attach it
under **Deck → Rendered video**, or set `video` to that media filename in the
flat social-post JSON and import through the site's normal `pw:flat:sync` workflow.
Use the configured content directory; see [carousel.md](carousel.md). Copying an
MP4 into a directory alone does not create a media-library entry.

Return the admin URL, the MP4 location and the editable project's location, with
the verification performed and any actual blocker. Keep failures explicit: a
failed upload or save does not constitute a delivered admin draft. Social-network
publishing is a separate action, outside this authoring workflow.
