import {spawn, spawnSync} from 'node:child_process';
import {watch} from 'node:fs';
import {copyFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import {build} from './build.mjs';

const [command, ...options] = process.argv.slice(2);
if (!['studio', 'render', 'still'].includes(command)) throw new Error('Expected studio, render or still.');
const video = await build();
let frame = 0;
let args;
if (command === 'still') {
  frame = Number((options[0] ?? '--frame=0').replace(/^--frame=/, ''));
  if (options.length > 1 || !Number.isSafeInteger(frame) || frame < 0 || frame >= video.durationInFrames) {
    throw new Error(`--frame must be an integer from 0 to ${video.durationInFrames - 1}.`);
  }
  args = ['snapshot', '--at', String(frame / video.fps), '--no-end', '--keep', '--output', 'out/stills', '--json'];
} else if (command === 'studio') {
  args = ['preview', '--no-open', '--foreground', ...options];
} else {
  args = ['render', '--output', 'out/video.mp4', '--workers', '2', ...options];
}
const cli = fileURLToPath(new URL('bin/hyperframes.mjs', import.meta.resolve('hyperframes/package.json')));
const settings = {
  cwd: fileURLToPath(new URL('../', import.meta.url)),
  env: {...process.env, HYPERFRAMES_NO_TELEMETRY: '1'},
};
if (command === 'studio') {
  let timer;
  const rebuild = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      // A fresh process reloads authored modules instead of Node's import cache.
      const result = spawnSync(process.execPath, ['src/build.mjs'], {...settings, stdio: 'inherit'});
      if (result.error) console.error(result.error);
    }, 100);
  };
  const watchers = ['../timeline.json', './'].map((path) => watch(new URL(path, import.meta.url), rebuild));
  const child = spawn(process.execPath, [cli, ...args], {...settings, stdio: 'inherit'});
  child.on('error', (error) => { throw error; });
  child.on('exit', (code) => {
    clearTimeout(timer);
    for (const watcher of watchers) watcher.close();
    process.exitCode = code ?? 1;
  });
} else {
  const result = spawnSync(process.execPath, [cli, ...args], {
    ...settings, stdio: command === 'still' ? ['ignore', 'pipe', 'inherit'] : 'inherit', encoding: 'utf8',
  });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
  if (command === 'still') {
    const snapshot = JSON.parse(result.stdout);
    await copyFile(snapshot.frames[0].path, new URL('../out/preview.png', import.meta.url));
    console.log(JSON.stringify({result: 'done', frame, file: 'out/preview.png'}));
  }
}
