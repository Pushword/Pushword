# NORD. — Motion study

A 17.6-second, 1080 × 1920 reel with five custom scenes. Its canonical source,
photos and preparation script ship in
[`packages/repurpose/examples/nord/`](../../../../repurpose/examples/nord/README.md),
so the same example works on a Composer site without the development app.

`AppFixtures` creates an Instagram social post (`standalone/motion-nord`) and a
media entry for `media~/repurpose-nord.mp4`. Open that post in the admin studio:
**Rendered video** plays the committed MP4; **Slides** shows the static companion
deck from the package example's `spec.json`. Loading fixtures does not require
Node.js or a video render.

To edit the animation, prepare a new Remotion project from the repository root
(Node.js 22+ and npm required):

```sh
node packages/repurpose/examples/nord/prepare.mjs /tmp/nord-motion
cd /tmp/nord-motion
npm install
npm run check
npm run studio
npm run render
```

The preparation script copies the Remotion template, scenes and local assets,
then sets the timing in `timeline.json`. All animation derives from frame numbers.
Edit the generated project; update the package example's source if the fixture
itself changes.

The new output is `out/video.mp4`. Import it into the media library and choose it
under **Deck → Rendered video** to preview it in the admin. To update the bundled
fixture, replace `packages/dev-app/media~/repurpose-nord.mp4`. Editing slides in
the admin does not re-render the motion project.
