import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {pathToFileURL} from 'node:url';
import {test} from 'node:test';
import {audioMix} from '../../src/Resources/motion/src/audio.mjs';
import {soundtrack} from '../../src/Resources/motion/src/soundtrack.mjs';

function wav(seconds, frequency, pauses = false, rightOnly = false) {
  const channels = rightOnly ? 2 : 1;
  const bytes = Buffer.alloc(44 + seconds * 8000 * channels * 2);
  bytes.write('RIFF'); bytes.writeUInt32LE(bytes.length - 8, 4); bytes.write('WAVEfmt ', 8);
  bytes.writeUInt32LE(16, 16); bytes.writeUInt16LE(1, 20); bytes.writeUInt16LE(channels, 22);
  bytes.writeUInt32LE(8000, 24); bytes.writeUInt32LE(16000 * channels, 28);
  bytes.writeUInt16LE(2 * channels, 32); bytes.writeUInt16LE(16, 34);
  bytes.write('data', 36); bytes.writeUInt32LE(bytes.length - 44, 40);
  for (let sample = 0; sample < seconds * 8000; sample++) {
    const time = sample / 8000;
    const audible = !pauses || (time >= 1 && time < 2) || (time >= 3 && time < 3.5);
    bytes.writeInt16LE(audible ? Math.round(Math.sin(2 * Math.PI * frequency * time) * 0.4 * 32767) : 0,
      44 + sample * channels * 2 + (rightOnly ? 2 : 0));
  }
  return bytes;
}

function tone(samples, start, frequency) {
  let real = 0, imaginary = 0;
  for (let index = 0; index < 1600; index++) {
    const angle = 2 * Math.PI * frequency * index / 8000;
    const sample = samples.readFloatLE((Math.round(start * 8000) + index) * 4);
    real += sample * Math.cos(angle);
    imaginary += sample * Math.sin(angle);
  }
  return 2 * Math.hypot(real, imaginary) / 1600;
}

test('the local soundtrack preserves levels, loops, fades, stereo and signal-driven ducking', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'pw-hyperframes-audio-'));
  const root = pathToFileURL(`${directory}/`);
  try {
    await mkdir(join(directory, 'public'));
    await writeFile(join(directory, 'public/voice.wav'), wav(4, 440, true));
    await writeFile(join(directory, 'public/music.wav'), wav(0.5, 1000));
    await writeFile(join(directory, 'public/stereo.wav'), wav(0.5, 1000, false, true));
    const samples = async (audio, channel = 0) => {
      const output = await soundtrack({fps: 30, durationInFrames: 270, audio: audioMix(audio)}, root);
      return execFileSync('ffmpeg', ['-v', 'error', '-i', join(directory, output), '-af', `pan=mono|c0=c${channel}`, '-ar', '8000', '-f', 'f32le', 'pipe:1']);
    };
    const music = {src: 'music.wav', volume: 0.5};
    const voice = {src: 'voice.wav'};
    const plain = await samples({music});
    const level = tone(plain, 1.5, 1000);
    assert.equal(plain.length / 4, 9 * 8000);
    assert(Math.abs(level - 0.2) < 0.01);
    assert(Math.abs(tone(plain, 6, 1000) - level) < 0.01, 'short music loops to the end');
    assert(Math.abs(tone(await samples({music: {...music, volume: 0.25}}), 1.5, 1000) - level / 2) < 0.01);
    assert(tone(await samples({music: {...music, volume: 0}}), 1.5, 1000) < 0.001);
    const faded = await samples({music: {...music, fadeIn: 1, fadeOut: 1}});
    assert(tone(faded, 0.1, 1000) < level / 2);
    assert(tone(faded, 8.7, 1000) < level / 2);
    assert(Math.abs(tone(faded, 5, 1000) - level) < 0.01);
    const tinyFades = await samples({music: {...music, fadeIn: 1e-7, fadeOut: 1e-7}});
    assert(Math.abs(tone(tinyFades, 0.1, 1000) - level) < 0.01, 'sub-millisecond fades must not stretch or break FFmpeg');
    const ducked = await samples({voice, music});
    const unDucked = await samples({voice, music, ducking: false});
    assert(tone(ducked, 1.5, 1000) < level / 3);
    assert(Math.abs(tone(unDucked, 1.5, 1000) - level) < 0.01);
    assert(tone(ducked, 2.7, 1000) > level * 0.8, 'music recovers during pauses');
    assert(Math.abs(tone(ducked, 6, 1000) - level) < 0.01);
    assert(Math.abs(tone(ducked, 1.5, 440) - tone(unDucked, 1.5, 440)) < 0.01);
    assert(Math.abs(tone(await samples({voice: {...voice, volume: 0}, music}), 1.5, 1000) - level) < 0.01);
    const stereo = {src: 'stereo.wav', volume: 0.5};
    assert(tone(await samples({voice: {...voice, volume: 0}, music: stereo}), 1.5, 1000) < 0.001);
    assert(Math.abs(tone(await samples({voice: {...voice, volume: 0}, music: stereo}, 1), 1.5, 1000) - level) < 0.01);
    const narration = await samples({voice: {...voice, volume: 0.5, fadeIn: 2, fadeOut: 1}});
    assert(tone(narration, 1.1, 440) < 0.15);
    assert(tone(narration, 3.2, 440) < 0.15);
    assert(tone(narration, 6, 440) < 0.001);
    const first = await readFile(join(directory, 'public/generated/soundtrack.wav'));
    await samples({voice: {...voice, volume: 0.5, fadeIn: 2, fadeOut: 1}});
    assert.deepEqual(await readFile(join(directory, 'public/generated/soundtrack.wav')), first);
    assert.equal(await soundtrack({fps: 30, durationInFrames: 30, audio: audioMix()}, root), null);
  } finally {
    await rm(directory, {recursive: true, force: true});
  }
});
