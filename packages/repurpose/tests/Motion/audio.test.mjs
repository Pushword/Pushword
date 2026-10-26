import assert from 'node:assert/strict';
import {test} from 'node:test';
import {audioMix} from '../../src/Resources/motion/src/audio.mjs';
import {timeline} from '../../src/Resources/motion/src/timeline.mjs';

test('audio defaults and validation preserve silence, reject unsafe sources and invalid gains', () => {
  assert.deepEqual(audioMix(), {ducking: true});
  const mix = audioMix({voice: {src: 'audio/voice.wav', volume: 0}, music: {src: 'audio/music.mp3'}, ducking: false});
  assert.equal(mix.voice.volume, 0);
  assert.equal(mix.music.volume, 0.3);
  assert.equal(mix.ducking, false);
  for (const src of ['../a.mp3', '/tmp/a.wav', 'https://test/a.mp3', 'a/../../x.wav', '']) {
    assert.throws(() => audioMix({music: {src}}), /audio.music.src/);
  }
  for (const [key, value] of [['volume', -0.1], ['volume', 1.1], ['fadeIn', -1], ['fadeOut', 31], ['volume', NaN]]) {
    assert.throws(() => audioMix({voice: {src: 'audio/a.wav', [key]: value}}), new RegExp(`audio.voice.${key}`));
  }
  assert.throws(() => audioMix({ducking: 'false'}), /audio.ducking/);
  const video = timeline({width: 2, height: 2, fps: 30, slides: [{id: 'a', layers: [], durationInFrames: 90, transitionInFrames: 0}], audio: {music: {src: 'music.wav'}}});
  assert.equal(video.audio.music.src, 'music.wav');
});
