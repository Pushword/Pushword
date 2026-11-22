import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../src/Resources/public/repurpose.js', import.meta.url), 'utf8');
const origin = 'https://example.test';
function studio(config = {}, fetch, confirm, globals = {}) {
  let factory;
  const window = {location: {origin}, confirm, RP_STUDIO: {
    spec: {slides: [{title: 'Test', images: [{}]}]},
    urls: {mediaIndex: '/admin/media', mediaUpload: '/admin/media/new'},
    messages: {videoInvalid: 'Choose a video', audioInvalid: 'Choose audio'},
    ...config,
  }, Alpine: {data: (_, value) => { factory = value; }}, addEventListener() {}, removeEventListener() {}};
  vm.runInNewContext(source, {window, fetch, document: {addEventListener: (_, callback) => callback()}, ...globals});
  return factory();
}
function selection(fileName, eventOrigin = origin) {
  return {origin: eventOrigin, data: {type: 'pw-media-picker-select', media: {fileName}}};
}

test('slide guides mark peer edges and centers until the drag ends', async () => {
  const moved = {content: 'Move this', x: 0.1, y: 0.2, width: 0.3};
  const preview = ['<svg/>'];
  let overlay;
  const state = studio({spec: {slides: [{texts: [moved]}]}},
    async () => ({ok: true, json: async () => ({slides: preview})}), undefined, {
      document: {
        addEventListener: (_, callback) => callback(),
        createElement: () => ({style: {}, lines: [], setAttribute() {},
          append(line) { this.lines.push(line); }, remove() { overlay = undefined; }}),
      },
      performance: {now: () => 1000},
    });
  state.$nextTick = () => {};
  let transform;
  const block = {
    getAttribute: () => 'texts.0',
    getBBox: () => assert.fail('the dragged block must not contribute guides'),
    setAttribute: (_, value) => { transform = value; },
    removeAttribute: () => { transform = undefined; },
    ownerSVGElement: {
      viewBox: {baseVal: {x: 0, y: 0, width: 1000, height: 500}},
      getBoundingClientRect: () => ({width: 500, height: 250}),
      querySelectorAll: () => [block,
        {getBBox: () => ({x: 200, y: 100, width: 400, height: 200})},
        {getBBox: () => ({x: 0, y: 0, width: 1000, height: 200})}],
      after: element => { overlay = element; },
    },
  };
  const start = {target: {closest: () => block}, button: 0, pointerId: 1, clientX: 100, clientY: 100, preventDefault() {}};
  const move = {pointerId: 1, clientX: 127, clientY: 115};
  state.deckTextDown(0, start);
  state.deckTextMove({...move, clientX: 102, clientY: 100});
  assert.equal(overlay, undefined, 'clicks do not display guides');
  state.deckTextMove(move);
  assert.ok(overlay, 'the guides are visible after moving');
  assert.deepEqual(overlay.lines.map(line => line.style.left).filter(Boolean).sort(), ['20%', '40%', '50%', '60%']);
  assert.deepEqual(overlay.lines.map(line => line.style.top).filter(Boolean).sort(), ['20%', '40%', '60%']);
  assert.equal(transform, 'translate(54.0 30.0)');
  state.deckTextDown(0, {...start, pointerId: 2});
  state.deckTextMove({...move, pointerId: 2, clientX: 150});
  state.deckTextUp({pointerId: 2});
  assert.ok(overlay, 'a second pointer must not end the first drag');
  assert.equal(transform, 'translate(54.0 30.0)');
  await state.preview();
  assert.notEqual(state.slides, preview, 'a pending preview must not replace the slide during the drag');
  state.deckTextCancel({pointerId: 1});
  assert.equal(state.slides, preview);
  assert.equal(overlay, undefined);
  assert.equal(transform, undefined);
  assert.equal(state.spec.slides[0].texts[0].x, 0.1);

  state.deckTextDown(0, start);
  state.deckTextMove(move);
  state.deckTextUp({pointerId: 1});
  assert.equal(overlay, undefined);
  assert.equal(state.spec.slides[0].texts[0].x, 0.154);
  assert.equal(state.spec.slides[0].texts[0].y, 0.26);
});

test('audio errors stay attached to the exact track and field and clear after validation', () => {
  const state = studio();
  state.violations = [
    {path: 'slides[0].title', message: 'Add a title'},
    {path: 'audio.voice.volume', message: 'Voice volume is out of range'},
    {path: 'audio.music.fadeOut', message: 'Music fade is out of range'},
  ];
  assert.equal(state.audioError('voice', 'volume'), 'Voice volume is out of range');
  assert.equal(state.audioError('music', 'fadeOut'), 'Music fade is out of range');
  assert.equal(state.audioError('music', 'volume'), '');
  assert.equal(state.audioError('voice', 'fadeOut'), '');
  state.violations = [];
  assert.equal(state.audioError('voice', 'volume'), '');
});

test('a video export refused for an audio file shows the error on that track', async () => {
  const violation = {path: 'audio.voice.media', message: 'Audio media is missing or unsupported: gone.wav'};
  const state = studio({urls: {exportVideo: '/export-video'}},
    async () => ({ok: false, status: 422, json: async () => ({violations: [violation]})}), undefined, {
      document: {addEventListener: (_, callback) => callback(), querySelectorAll: () => [{getAttribute: () => '64'}],
        createElement: () => ({getContext: () => ({drawImage() {}}), toDataURL: () => 'data:image/png;base64,AA=='})},
      XMLSerializer: class { serializeToString() { return '<svg/>'; } },
      btoa: value => Buffer.from(value, 'binary').toString('base64'),
      Image: class { set src(_) { this.onload(); } },
      setTimeout() {},
    });
  await state.exportVideo();
  assert.equal(state.audioError('voice', 'media'), violation.message);
  assert.equal(state.notice, violation.message);
});

test('network switching names the destination and preserves edits when cancelled', () => {
  let message;
  const state = studio({network: 'instagram', networkUrls: {youtube: '/youtube'}, vocab: {networkLabels: {youtube: 'YouTube Shorts'}}}, undefined,
    value => { message = value; return false; });
  state.dirty = true;
  const event = {target: {value: 'youtube'}};
  state.switchNetwork(event);
  assert.equal(message, 'Discard unsaved changes and switch to YouTube Shorts?');
  assert.equal(event.target.value, 'instagram');
  assert.equal(state.dirty, true);
  assert.equal(state.spec.slides[0].title, 'Test');
});

test('voice and music pickers keep independent levels, fades and mute in the saved payload', () => {
  const state = studio();
  state.normalize();
  assert.equal(state.payload().audio, undefined, 'empty controls must not create audio tracks');
  state.pickAudio('voice', 'choose');
  state.onMediaMessage(selection('narration.wav'));
  state.spec.audio.voice.volume = 0;
  state.spec.audio.voice.fadeOut = 0.5;
  state.pickAudio('music', 'upload');
  state.onMediaMessage(selection('picture.jpg'));
  assert.equal(state.notice, 'Choose audio');
  assert.equal(state.mediaModal.open, true);
  state.onMediaMessage(selection('music.MP3', 'https://other.test'));
  assert.equal(state.spec.audio.music.media, '');
  state.onMediaMessage(selection('music.MP3'));
  assert.equal(state.spec.audio.music.volume, 0.3);
  assert.equal(state.spec.audio.voice.media, 'narration.wav');
  assert.equal(state.payload().audio.voice.volume, 0);
  assert.equal(state.payload().audio.voice.fadeOut, 0.5);
  assert.equal(state.payload().audio.ducking, true);
  state.spec.audio.voice.media = '';
  state.spec.audio.ducking = false;
  assert.equal(state.payload().audio.voice, undefined);
  assert.equal(state.payload().audio.music.media, 'music.MP3');
  assert.equal(state.payload().audio.ducking, false);
  assert.equal(state.spec.slides[0].images[0].media, undefined);
});

test('choosing a rendered video switches preview without changing slide images', () => {
  const state = studio();
  state.pickVideo('choose');
  assert.equal(state.mediaModal.src, '/admin/media');
  state.onMediaMessage(selection('reel.mp4'));
  assert.equal(state.spec.video, 'reel.mp4');
  assert.equal(state.selectedVideo, 'reel.mp4');
  assert.equal(state.previewMode, 'video');
  assert.equal(state.mediaModal.open, false);
  assert.equal(state.spec.slides[0].images[0].media, undefined);
  assert.equal(state.payload().video, 'reel.mp4');
  state.clearVideo();
  assert.equal(state.video, null);
  assert.equal(state.selectedVideo, '');
  assert.equal(state.payload().video, undefined);
});

test('video picker rejects unsupported media and messages from other origins', () => {
  const state = studio();
  state.pickVideo('upload');
  assert.equal(state.mediaModal.src, '/admin/media/new');
  state.onMediaMessage(selection('picture.jpg'));
  assert.equal(state.notice, 'Choose a video');
  assert.equal(state.mediaModal.open, true);
  assert.equal(state.spec.video, undefined);
  state.onMediaMessage(selection('reel.mp4', 'https://other.test'));
  assert.equal(state.spec.video, undefined);
  state.onMediaMessage(selection('reel.WEBM'));
  assert.equal(state.spec.video, 'reel.WEBM');
});

test('image picker still writes its own cell and ignores late selections', () => {
  const state = studio();
  state.pickMedia(0, 0, 'choose');
  state.onMediaMessage(selection('photo.jpg'));
  assert.equal(state.spec.slides[0].images[0].media, 'photo.jpg');
  assert.equal(state.spec.video, undefined);
  state.onMediaMessage(selection('late.jpg'));
  assert.equal(state.spec.slides[0].images[0].media, 'photo.jpg');
});

test('an attached render opens in video mode and preview refreshes its media URL', async () => {
  const video = {name: 'reel.mp4', url: '/media/reel.mp4'};
  let nextVideo = video;
  const state = studio({spec: {video: 'reel.mp4', slides: [{title: 'Test'}]}, video},
    async () => ({ok: true, json: async () => ({slides: ['<svg/>'], video: nextVideo})}));
  assert.equal(state.previewMode, 'video');
  assert.equal(state.video.url, '/media/reel.mp4');
  state.videoError = true;
  await state.preview();
  assert.equal(state.live.state, 'ok');
  assert.equal(state.videoError, true, 'editing slides must not hide an existing playback error');
  nextVideo = {name: 'replacement.webm', url: '/media/replacement.webm'};
  state.spec.video = 'replacement.webm';
  await state.preview();
  assert.equal(state.video.url, '/media/replacement.webm');
  assert.equal(state.selectedVideo, 'replacement.webm');
  assert.equal(state.videoError, false);
  nextVideo = null;
  state.spec.video = 'missing.mp4';
  await state.preview();
  assert.equal(state.video, null);
  assert.equal(state.selectedVideo, 'missing.mp4');
  assert.equal(state.slides.length, 1);
});
