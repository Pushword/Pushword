import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {copyFile, cp, mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {fileURLToPath} from 'node:url';
import {test} from 'node:test';

test('packaged example prepares and validates without the monorepo or PHP', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'pw-nord-example-'));
  const packageDir = join(directory, 'vendor/pushword/repurpose');
  try {
    await cp(new URL('../../examples/nord/', import.meta.url), join(packageDir, 'examples/nord'), {recursive: true});
    await cp(new URL('../../src/Resources/motion/', import.meta.url), join(packageDir, 'src/Resources/motion'), {recursive: true});
    await mkdir(join(packageDir, 'src/Resources/font'));
    for (const name of ['montserrat.ttf', 'work-sans.ttf']) {
      await copyFile(new URL(`../../src/Resources/font/${name}`, import.meta.url), join(packageDir, 'src/Resources/font', name));
    }
    const out = join(directory, 'my reel');
    const prepared = spawnSync(process.execPath, [join(packageDir, 'examples/nord/prepare.mjs'), out],
      {cwd: directory, env: {...process.env, PATH: ''}, encoding: 'utf8'});
    assert.equal(prepared.status, 0, prepared.stderr);
    assert.equal(JSON.parse(prepared.stdout).project, out);
    const check = spawnSync(process.execPath, [join(out, 'src/check.mjs')], {encoding: 'utf8'});
    assert.equal(check.status, 0, check.stderr);
    assert.deepEqual(JSON.parse(check.stdout), {result: 'passed', slides: 5, assets: 0, durationInFrames: 528, seconds: 17.6});
    for (const name of ['package.json', 'EXAMPLE.md', 'spec.json', '.gitignore', 'src/scenes.jsx',
      'public/photos/aurora.jpg', 'public/photos/camp.jpg', 'public/fonts/montserrat.ttf', 'public/fonts/work-sans.ttf']) {
      assert((await readFile(join(out, name))).length > 0, `${name} must be included`);
    }
    const spec = JSON.parse(await readFile(join(out, 'spec.json'), 'utf8'));
    assert.equal(await readFile(join(out, 'caption.txt'), 'utf8'), `${spec.caption}\n\n${spec.hashtags.map((tag) => `#${tag}`).join(' ')}\n`);
  } finally {
    await rm(directory, {recursive: true, force: true});
  }
});

test('preparing an example refuses an existing project and preserves its scenes', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'pw-nord-existing-'));
  try {
    const prepare = fileURLToPath(new URL('../../examples/nord/prepare.mjs', import.meta.url));
    const run = () => spawnSync(process.execPath, [prepare, directory], {encoding: 'utf8'});
    assert.notEqual(run().status, 0, 'even an empty existing directory must be refused');
    await mkdir(join(directory, 'src'));
    await writeFile(join(directory, 'src/scenes.jsx'), 'authored scene');
    assert.notEqual(run().status, 0);
    assert.equal(await readFile(join(directory, 'src/scenes.jsx'), 'utf8'), 'authored scene');
  } finally {
    await rm(directory, {recursive: true, force: true});
  }
});
