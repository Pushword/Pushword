import assert from 'node:assert/strict';
import {test} from 'node:test';
import {audioMix, duckingEnvelope, trackVolume} from '../../src/Resources/motion/src/audio.mjs';
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

test('fades use the whole timeline across music loops and stop at the voice end', () => {
  const track = {volume: 0.6, fadeIn: 1, fadeOut: 1};
  assert.equal(trackVolume(track, 0, 30, 180), 0);
  assert.equal(trackVolume(track, 15, 30, 180), 0.3);
  assert.equal(trackVolume(track, 90, 30, 180), 0.6);
  assert.equal(trackVolume(track, 164, 30, 180), 0.3);
  assert.equal(trackVolume(track, 179, 30, 180), 0);
  assert.equal(trackVolume(track, 180, 30, 180), 0);
  assert.equal(trackVolume({...track, fadeIn: 0, fadeOut: 0}, 0, 30, 1), 0.6);
  assert.equal(trackVolume({...track, volume: 0}, 90, 30, 180), 0);
});

test('music ducks during voice, recovers during pauses and after it, and ignores muted voice', () => {
  const channel = new Float32Array(3000);
  channel.fill(0.4, 1000, 1500);
  channel.fill(0.4, 2500);
  const data = {sampleRate: 1000, durationInSeconds: 3, channelWaveforms: [new Float32Array(3000), channel]};
  const voice = {volume: 1, fadeIn: 0, fadeOut: 0};
  const gains = duckingEnvelope(data, voice, 30, 180);
  assert.equal(gains[0], 1);
  assert(gains[40] < 0.2, 'audible voice must lower music');
  assert(gains[70] > 0.8, 'a pause must restore music');
  assert(gains[89] < 0.2);
  assert(gains[150] > 0.99, 'music must recover after the recording ends');
  assert.deepEqual(duckingEnvelope(data, {...voice, volume: 0}, 30, 180), Array(180).fill(1));
  assert.deepEqual(duckingEnvelope(data, voice, 30, 180), gains, 'seeking must not change gains');
});
