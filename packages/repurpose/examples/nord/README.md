# NORD. — Editable motion example

A complete 17.6-second, 1080 × 1920 reel: masked typography, photo moves, a drawn
route, a constellation, staggered words and a circular outro. This example ships
with `pushword/repurpose`, including its two demo photos, which are AI-generated,
royalty-free images. Preparation uses the package's bundled fonts and HyperFrames
template; it needs no PHP, database, media library or development app. Rendering
requires Node.js 22+, npm and FFmpeg (including ffprobe).

From a Composer site's root, prepare a **new** directory (its parent must exist):

```sh
node vendor/pushword/repurpose/examples/nord/prepare.mjs /tmp/nord-motion
cd /tmp/nord-motion
npm install
npm run check
npm run still -- --frame=205
npm run studio
npm run render
```

In the Pushword monorepo, replace `vendor/pushword/repurpose` with
`packages/repurpose`. Existing output directories are refused, never overwritten.
Keep the generated project and its npm lockfile outside `vendor/`.

## Adapt it

- `src/scenes.mjs`: copy, layout and frame-based animation. These five scenes
  supply their own artwork, so their timeline `layers` arrays are empty.
- `timeline.json`: scene order, durations and crossfades. Scene starts are frames
  0, 110, 218, 330 and 408; the last frame is 527. A change in timing changes those
  numbers. The example's artwork is designed for 1080 × 1920; changing dimensions
  also requires adapting its layouts.
- `public/photos/` and `public/fonts/`: local artwork and fonts. Replace them as
  needed; no remote asset is required.
- `spec.json`: the companion static carousel. Keep its copy in sync deliberately:
  editing this JSON does not change the custom HTML/JavaScript scenes, or vice versa.

Read the generated README for the shared scene API. Inspect the entrance, middle,
transition and final frames before delivering `out/video.mp4`. In particular,
check text against animated paths and images at mobile size. `npm run check`
validates timing and declared SVG layers; it cannot detect overlaps or check the
custom scenes' photos and fonts. Stills compile the scenes and exercise those assets.

## Show it in the admin

For a new post, choose its own `page` key (e.g. `standalone/my-reel`) and replace
the copy in both the spec and scenes. The sample spec's `1.jpg` and `3.jpg` refer
to the development fixtures: on another site, import `photos/aurora.jpg` and
`photos/camp.jpg` into the media library and use their returned filenames in the
spec, or use that site's own images. The MP4 itself is independent of these media
library references.

Upload the finished MP4, set `spec.video` to the **returned** media filename, then
validate and save the post. The media API may rename or deduplicate an upload.
Open the returned `studioUrl` and select **Rendered video**. The admin plays this
render; editing slides there does not regenerate it.

The `pw motion` playbook in the Pushword AI skills provides the complete API
workflow, including upload, validation, save and verification. The package docs
describe the same integration in `extension/repurpose.md`.

## Development fixture

`AppFixtures` reads this example's `spec.json` and associates the committed
`packages/dev-app/media~/repurpose-nord.mp4`. Fixture loading needs no Node.js or
rendering tools. If the fixture artwork changes, update these sources and replace
that MP4 with a fresh render; do not create a second copy of the scene source.
