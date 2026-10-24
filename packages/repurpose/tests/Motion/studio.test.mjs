import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../src/Resources/public/repurpose.js', import.meta.url), 'utf8');
const origin = 'https://example.test';
function studio(config = {}, fetch, confirm) {
  let factory;
  const window = {location: {origin}, confirm, RP_STUDIO: {
    spec: {slides: [{title: 'Test', images: [{}]}]},
    urls: {mediaIndex: '/admin/media', mediaUpload: '/admin/media/new'},
    messages: {videoInvalid: 'Choose a video'},
    ...config,
  }, Alpine: {data: (_, value) => { factory = value; }}};
  vm.runInNewContext(source, {window, fetch, document: {addEventListener: (_, callback) => callback()}});
  return factory();
}
function selection(fileName, eventOrigin = origin) {
  return {origin: eventOrigin, data: {type: 'pw-media-picker-select', media: {fileName}}};
}

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
