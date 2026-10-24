import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {copyFile, mkdir, mkdtemp, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {test} from 'node:test';
import {timeline, entranceOpacity, layerStyle} from '../../src/Resources/motion/src/timeline.mjs';

const slide = (id, durationInFrames = 90, transitionInFrames = 0) => ({
  id, durationInFrames, transitionInFrames,
  layers: [{id: 'title', src: `slides/${id}/title.svg`, animation: 'rise'}],
});
const project = (slides) => ({width: 1080, height: 1920, fps: 30, slides});

test('cuts and overlapping fades have exact frame counts, including the last scene', () => {
  const video = timeline(project([slide('one'), slide('two', 60, 12), slide('three', 30, 6)]));
  assert.deepEqual(video.slides.map(({from}) => from), [0, 78, 132]);
  assert.equal(video.durationInFrames, 162);
  assert.equal(timeline(project([slide('one', 1)])).durationInFrames, 1);
  assert.equal(timeline(project([slide('one'), slide('two')])).durationInFrames, 180);
});

test('invalid timing and duplicate scene ids report the affected path', () => {
  for (const [slides, path] of [
    [[], 'slides'],
    [[slide('one', 0)], 'slides[0].durationInFrames'],
    [[slide('one', 2.5)], 'slides[0].durationInFrames'],
    [[slide('one', 90, 1)], 'slides[0].transitionInFrames'],
    [[slide('one'), slide('one')], 'slides[1].id'],
    [[slide('one'), slide('two', 10, 10)], 'slides[1].transitionInFrames'],
    [[slide('one'), slide('two', 20, 12), slide('three', 90, 8)], 'slides[2].transitionInFrames'],
  ]) {
    assert.throws(() => timeline(project(slides)), (error) => error.message.includes(path));
  }
});

test('dimensions and fps are valid for an H.264 composition', () => {
  for (const [key, value] of [['width', 1081], ['height', 0], ['fps', 0], ['fps', 61], ['fps', 29.97]]) {
    assert.throws(() => timeline({...project([slide('one')]), [key]: value}), new RegExp(key));
  }
});

test('assets stay local and layer ids and animation presets are checked', () => {
  for (const src of ['../secret.svg', '/tmp/a.svg', 'https://host/a.svg', 'slides/../../a.svg', '']) {
    const item = slide('one');
    item.layers[0].src = src;
    assert.throws(() => timeline(project([item])), /layers\[0\].src/);
  }
  const item = slide('one');
  item.layers[0].animation = 'typo';
  assert.throws(() => timeline(project([item])), /animation/);
  item.layers[0].animation = 'none';
  item.layers.push({...item.layers[0]});
  assert.throws(() => timeline(project([item])), /layers\[1\].id/);
  item.layers = [];
  assert.equal(timeline(project([item])).slides.length, 1, 'custom scenes may supply all their own artwork');
});

test('frame-addressed animations clamp and do not depend on playback order', () => {
  assert.equal(entranceOpacity(0, 12), 0);
  assert.equal(entranceOpacity(11, 12), 1);
  assert.equal(entranceOpacity(25, 12), 1);
  assert.equal(entranceOpacity(0, 0), 1);
  assert.equal(entranceOpacity(0, 1), 1);
  assert.deepEqual(layerStyle('none', 10, 30, 90), {});
  assert.deepEqual(layerStyle('rise', 0, 30, 90), {opacity: 0, transform: 'translateY(4%)'});
  assert.deepEqual(layerStyle('rise', 14, 30, 90), {opacity: 1, transform: 'translateY(0%)'});
  assert.deepEqual(layerStyle('fade', -1, 30, 90), {opacity: 0});
  assert.deepEqual(layerStyle('fade', 0, 30, 1), {opacity: 1});
  assert.deepEqual(layerStyle('rise', 2, 30, 3), {opacity: 1, transform: 'translateY(0%)'});
  assert.deepEqual(layerStyle('zoom-in', 89, 30, 90), {transform: 'scale(1.04)'});
  const frame = layerStyle('rise', 7, 30, 90);
  layerStyle('rise', 80, 30, 90);
  assert.deepEqual(layerStyle('rise', 7, 30, 90), frame);
});

test('project check counts existing assets and fails for a missing file', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'pw-motion-check-'));
  try {
    await mkdir(join(directory, 'src'));
    await mkdir(join(directory, 'public/slides/one'), {recursive: true});
    for (const name of ['check.mjs', 'timeline.mjs']) {
      await copyFile(new URL(`../../src/Resources/motion/src/${name}`, import.meta.url), join(directory, 'src', name));
    }
    await writeFile(join(directory, 'timeline.json'), JSON.stringify(project([slide('one')])));
    const run = () => spawnSync(process.execPath, [join(directory, 'src/check.mjs')], {encoding: 'utf8'});
    const missing = run();
    assert.equal(missing.status, 1);
    assert.equal(JSON.parse(missing.stderr).result, 'failed');
    assert.equal(missing.stdout, '');
    await writeFile(join(directory, 'public/slides/one/title.svg'), '<svg/>');
    const present = run();
    assert.equal(present.status, 0);
    assert.deepEqual(JSON.parse(present.stdout), {result: 'passed', slides: 1, assets: 1, durationInFrames: 90, seconds: 3});
    assert.equal(present.stderr, '');
  } finally {
    await rm(directory, {recursive: true, force: true});
  }
});
