import assert from 'node:assert/strict';
import {test} from 'node:test';
import {createTimeline} from '../../src/Resources/motion/src/player.mjs';
import {timeline} from '../../src/Resources/motion/src/timeline.mjs';

test('HyperFrames seeks custom scenes in either direction with local frames and overlapping fades', () => {
  const video = timeline({width: 1080, height: 1920, fps: 30, slides: [
    {id: 'one', durationInFrames: 90, transitionInFrames: 0, layers: []},
    {id: 'two', durationInFrames: 60, transitionInFrames: 12, layers: []},
  ]});
  const elements = [{style: {}}, {style: {}}];
  const frames = {};
  const scene = {render: (_, frame, slide) => { frames[slide.id] = frame; }};
  const player = createTimeline(video, {children: elements}, {one: scene, two: scene});
  assert.equal(player.duration(), 4.6);
  player.seek(79 / 30);
  assert.deepEqual(frames, {one: 79, two: 1});
  assert.equal(elements[1].style.opacity, 1 / 11);
  assert.equal(elements[0].style.visibility, 'visible');
  assert.equal(elements[1].style.visibility, 'visible');
  player.seek(100 / 30);
  assert.equal(elements[0].style.visibility, 'hidden');
  assert.equal(frames.two, 22);
  player.seek(79 / 30);
  assert.deepEqual(frames, {one: 79, two: 1});
  assert.equal(elements[1].style.opacity, 1 / 11);
  player.seek(100);
  assert.equal(frames.two, 59, 'the endpoint holds the last video frame');
  player.seek(-1);
  assert.equal(frames.one, 0);
  assert.equal(player.time(), 0);
  assert.equal(elements[1].style.visibility, 'hidden');
  assert.equal(player.pause(), player);
});

test('default SVG layers animate without a custom scene or an animation library', () => {
  const video = timeline({width: 64, height: 64, fps: 30, slides: [
    {id: 'one', durationInFrames: 30, transitionInFrames: 0, layers: [
      {id: 'title', src: 'title.svg', animation: 'rise'},
      {id: 'image', src: 'photo.svg', animation: 'zoom-in'},
    ]},
  ]});
  const images = [{style: {}}, {style: {}}];
  const element = {style: {}, querySelectorAll: () => images};
  const player = createTimeline(video, {children: [element]}, {});
  player.seek(0);
  assert.deepEqual(images[0].style, {opacity: 0, transform: 'translateY(4%)'});
  assert.deepEqual(images[1].style, {opacity: 1, transform: 'scale(1)'});
  player.seek(29 / 30);
  assert.deepEqual(images[0].style, {opacity: 1, transform: 'translateY(0%)'});
  assert.equal(images[1].style.transform, 'scale(1.04)');
  player.seek(0);
  assert.equal(images[0].style.opacity, 0);
});
