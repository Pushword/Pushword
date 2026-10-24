# NORD. — Motion study

A 17.6-second, 1080 × 1920 reel with five custom scenes: masked typography,
slow photo moves, a drawn route, a constellation, staggered words and a circular
outro. The photos and fonts already ship with the development app and Repurpose.
No remote assets or audio are required.

`AppFixtures` creates an Instagram social post (`standalone/motion-nord`) and a
media entry for `media~/repurpose-nord.mp4`. Open that post in the admin studio:
**Rendered video** plays the committed MP4; **Slides** shows the static companion
deck from `spec.json`. Loading fixtures does not require Node.js or a video render.

To edit the animation, prepare a new Remotion project from the repository root
(Node.js 22+ and npm required):

```sh
node packages/dev-app/src/DataFixtures/Motion/prepare.mjs /tmp/nord-motion
cd /tmp/nord-motion
npm install
npm run check
npm run studio
npm run render
```

The preparation script exports the base deck, installs `scenes.jsx`, copies local
assets and sets the timing in `timeline.json`. All animation derives from frame
numbers, so seeking and rendering use the same state. Edit the scene components
and timeline in the generated project; keep this source directory in sync if the
fixture itself changes.

The new output is `out/video.mp4`. Import it into the media library and choose it
under **Deck → Rendered video** to preview it in the admin. To update the bundled
fixture, replace `packages/dev-app/media~/repurpose-nord.mp4`. Editing slides in
the admin does not re-render the motion project.
