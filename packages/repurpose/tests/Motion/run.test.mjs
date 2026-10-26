import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {cp, mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {test} from 'node:test';

test('render commands propagate failures, capture the requested frame and disable telemetry', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'pw-hyperframes-run-'));
  try {
    await cp(new URL('../../src/Resources/motion/', import.meta.url), directory, {recursive: true});
    await writeFile(join(directory, 'timeline.json'), JSON.stringify({width: 64, height: 64, fps: 30, slides: [
      {id: 'only', durationInFrames: 30, transitionInFrames: 0, layers: []},
    ]}));
    await mkdir(join(directory, 'node_modules/hyperframes/bin'), {recursive: true});
    await writeFile(join(directory, 'node_modules/hyperframes/package.json'), JSON.stringify({
      name: 'hyperframes', type: 'module', exports: {'./package.json': './package.json'},
    }));
    await writeFile(join(directory, 'node_modules/hyperframes/bin/hyperframes.mjs'), `
      import {writeFileSync} from 'node:fs';
      import {resolve} from 'node:path';
      const args = process.argv.slice(2);
      writeFileSync('invocation.json', JSON.stringify({args, telemetry: process.env.HYPERFRAMES_NO_TELEMETRY}));
      if (args[0] === 'render') process.exit(7);
      writeFileSync('out/snapshot.png', 'captured frame');
      console.log(JSON.stringify({ok: true, frames: [{path: resolve('out/snapshot.png')}]}));
    `);
    const run = (...args) => spawnSync(process.execPath, [join(directory, 'src/run.mjs'), ...args], {encoding: 'utf8'});
    assert.equal(run('render').status, 7, 'a failed renderer must never report success');
    const still = run('still', '--frame=29');
    assert.equal(still.status, 0, still.stderr);
    assert.equal(JSON.parse(still.stdout).frame, 29);
    assert.equal(await readFile(join(directory, 'out/preview.png'), 'utf8'), 'captured frame');
    const invocation = JSON.parse(await readFile(join(directory, 'invocation.json'), 'utf8'));
    assert.equal(invocation.telemetry, '1');
    assert.equal(invocation.args[0], 'snapshot');
    assert.equal(Number(invocation.args[invocation.args.indexOf('--at') + 1]), 29 / 30);
    for (const option of ['--frame=-1', '--frame=30', '--frame=0.5', '--frame=abc']) {
      const invalid = run('still', option);
      assert.equal(invalid.status, 1);
      assert.match(invalid.stderr, /--frame must be an integer from 0 to 29/);
    }
  } finally {
    await rm(directory, {recursive: true, force: true});
  }
});
