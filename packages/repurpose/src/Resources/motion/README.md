# Repurpose motion project

An editable, local Remotion project. `spec.json` preserves the source carousel;
`timeline.json` controls this video's timing and layers. Editing one does not
rewrite the other. The SVG layers contain their fonts and images, so rendering
does not need the Pushword site to be running.

## Preview and render

Use Node.js 22 or later with npm:

```sh
npm install
npm run check
npm run studio
npm run still -- --frame=45
npm run render
```

`studio` runs an interactive preview server; use a separate terminal for render
commands, or stop it when finished previewing.

The MP4 is written to `out/video.mp4` (H.264, yuv420p). `still` writes
`out/preview.png`. Remotion downloads a headless browser when needed; its browser
must have the system libraries required by Chromium. Run `npm install` once and
keep the resulting `package-lock.json` with your project for repeatable installs.
Remotion dependencies are optional to Pushword and have their own
[license terms](https://www.remotion.dev/docs/license/pricing).

## Timeline authoring

`timeline.schema.json` describes `timeline.json`; `npm run check` validates timing,
layer references and asset availability and reports a JSON result.

- `width`, `height`: even pixel dimensions. Vertical Reels use `story-9-16` at
  export; other Pushword formats keep their dimensions.
- `fps`: integer 1–60, initially 30.
- `slides[]`: scene order. `id` is stable and identifies a custom scene.
- `durationInFrames`: scene length including its transitions. 90 frames = 3s at
  30fps. Changing fps changes the timing of existing frame counts.
- `transitionInFrames`: incoming crossfade; the first scene must use 0. It
  overlaps the previous scene, so total length is the sum of scene lengths minus
  these overlaps. Incoming and outgoing transitions must leave some hold time.
- `layers[]`: back-to-front compositing order. Each SVG is a transparent full-frame
  layer, preserving the original positioning, font, crop and highlight.
- `animation`: `none`, `fade`, `rise`, or `zoom-in`. Fade and rise enter during the
  first half-second (or the scene length if shorter); zoom goes from 1 to 1.04 over the scene. Use zoom on a
  full-frame image; scaling a split-image layer also moves its clipping boundary.

Layers have semantic ids: `background`, `images.0`, `images.1`, `overlay`,
`effect`, `tagline`, `title`, `paragraph`, `texts.0`, `creator`, `counter`, `swipe`.
Only layers present in the source slide are exported. Remove a layer from the
list to hide it; set all animations to `none` to reproduce the static slide.

## Voice and music

`audio.voice` and `audio.music` in `timeline.json` are independent optional tracks.
Each has a local `src` relative to `public/`, `volume` (0–1), `fadeIn` and `fadeOut`
in seconds (0–30). Voice defaults to volume 1; music to 0.3. Both start at frame 0.
Voice plays once and fades at its own end (or the video end if shorter); music
loops to the video end, with fades applied across the full composition.

`audio.ducking` defaults to true. The voice waveform controls a smooth reduction
in music volume while narration is audible, restoring it during pauses. Volume 0
mutes a track; muted voice does not reduce music. Audio gains are computed from
frame zero, so seeking and parallel rendering give the same result. Both source
files are copied from the site's library at export; no site connection is needed.
`npm run check` also checks the audio settings and files. Remove a track from
`audio` to detach it. Changing these settings does not modify `spec.json`.

## Agent-authored scenes

Edit `src/Scene.jsx` to change the shared animation style. For an entirely custom
scene, create a React component and register it by scene id in `src/scenes.jsx`:

```jsx
import React from 'react';
import {AbsoluteFill, useCurrentFrame, useVideoConfig} from 'remotion';
import {SlideLayers} from './Scene';

const Opening = ({slide}) => {
  const frame = useCurrentFrame(); // local to this scene, starting at 0
  const {fps} = useVideoConfig();
  const progress = Math.min(1, frame / fps);
  return <AbsoluteFill>
    <SlideLayers slide={slide}/>
    <div style={{position: 'absolute', bottom: 180, left: 80, color: '#f8fafc',
      fontSize: 64, opacity: progress}}>{Math.round(progress * 100)}%</div>
  </AbsoluteFill>;
};

export const scenes = {'slide-1': Opening};
```

`SlideLayers` is optional: a scene can supply all its own artwork. The enclosing
sequence applies its timeline duration and crossfade to either kind of scene.
Add local audio, video or illustrations under `public/` and use Remotion's
`staticFile()` and media components. Additional scene-specific media and their timing are managed in scene code.

All motion must derive from `useCurrentFrame()` and composition data. Do not use
CSS animation clocks, timers, `Date.now()` or `Math.random()` for rendered motion:
an agent and the renderer must be able to seek to any frame in any order. Use
Remotion's media components so rendering waits for fonts/images/media to load.

Agent loop: edit timeline or scene → `npm run check` → render stills at the
entrance, middle, transitions and last frame → inspect → `npm run render`.
`check` validates the timeline and its assets; rendering stills also compiles and
exercises custom JSX. Inspect custom scene assets separately.

## Return the video to Pushword

Upload `out/video.mp4` through the media library, or send a multipart `file` to
`POST /api/media/{filename}` with the site's API bearer token. Use the response's
`filename`: uploads can be renamed or deduplicated. Set that value in the
top-level `video` field of `spec.json`, keep the companion deck's media references
valid for the site, then validate and save the spec through
`POST /api/repurpose/validate` and `PUT /api/repurpose/{host}/{network}/{page}`.
PUT replaces the spec at that key; preserve existing post fields when updating.
If the upload is rejected for size, reduce the encoded file size or adjust the
site's PHP `upload_max_filesize` and `post_max_size` before uploading again.

The save response includes `studioUrl`. Open it and verify **Rendered video**
playback and seeking. Its `previewUrl` is the static deck. Editing slides in the
admin does not regenerate the video. Keep this project for later animation
revisions, and return both the MP4 and project location with the admin link.

Re-exporting to an existing directory is refused to preserve authored scenes.
Export revised carousel artwork to a new directory and copy the desired layers
into this project deliberately. No custom scene code is executed by the CMS.
