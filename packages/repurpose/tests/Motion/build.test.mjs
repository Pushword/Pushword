import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {cp, mkdtemp, readFile, readdir, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {test} from 'node:test';

test('a standalone project builds deterministic HyperFrames HTML and escapes timeline data', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'pw-hyperframes-build-'));
  try {
    await cp(new URL('../../src/Resources/motion/', import.meta.url), directory, {recursive: true});
    const id = 'one"><script>bad()</script>';
    await writeFile(join(directory, 'timeline.json'), JSON.stringify({width: 64, height: 96, fps: 24, slides: [
      {id, durationInFrames: 24, transitionInFrames: 0, layers: [{id: 'title', src: 'title.svg', animation: 'fade'}]},
      {id: 'custom', durationInFrames: 24, transitionInFrames: 6, layers: []},
    ]}));
    await writeFile(join(directory, 'src/scenes.mjs'), `export const scenes = {custom: {html: () => '<h1>Custom scene</h1>', render() {}}};`);
    const run = () => spawnSync(process.execPath, [join(directory, 'src/build.mjs')], {encoding: 'utf8'});
    const result = run();
    assert.equal(result.status, 0, result.stderr);
    const html = await readFile(join(directory, 'index.html'), 'utf8');
    assert.match(html, /data-composition-id="Repurpose"/);
    assert.match(html, /data-duration="1.75"/);
    assert.match(html, /data-fps="24"/);
    assert.match(html, /data-start="0.75"/);
    const first = await readFile(join(directory, 'compositions/scene-0.html'), 'utf8');
    assert.match(first, /src="..\/public\/title.svg"/);
    assert.match(await readFile(join(directory, 'compositions/scene-1.html'), 'utf8'), /<h1>Custom scene<\/h1>/);
    assert.match(html, /window\.__timelines.Repurpose = player/);
    assert(!html.includes('<script>bad()'));
    assert(!html.includes('<audio'), 'a silent project needs no FFmpeg or generated soundtrack');
    assert.equal(run().status, 0);
    assert.equal(await readFile(join(directory, 'index.html'), 'utf8'), html);
    await writeFile(join(directory, 'timeline.json'), JSON.stringify({width: 64, height: 96, fps: 24, slides: [
      {id: 'only', durationInFrames: 1, transitionInFrames: 0, layers: []},
    ]}));
    assert.equal(run().status, 0);
    assert.deepEqual(await readdir(join(directory, 'compositions')), ['scene-0.html']);
  } finally {
    await rm(directory, {recursive: true, force: true});
  }
});

test('the exported renderer uses only HyperFrames', async () => {
  const pkg = JSON.parse(await readFile(new URL('../../src/Resources/motion/package.json', import.meta.url), 'utf8'));
  assert.deepEqual(pkg.dependencies, {hyperframes: '0.8.204'});
});
