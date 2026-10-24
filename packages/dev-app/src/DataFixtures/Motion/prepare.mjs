import {execFileSync} from 'node:child_process';
import {copyFile, mkdir, readFile, writeFile} from 'node:fs/promises';
import {resolve, join} from 'node:path';
import {fileURLToPath} from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const root = fileURLToPath(new URL('../../../../../', import.meta.url));
if (!process.argv[2]) throw new Error('Usage: node prepare.mjs <new-project-directory>');
const out = resolve(process.argv[2]);
execFileSync('php', [join(root, 'packages/dev-app/bin/console'), 'pw:repurpose:motion',
  join(here, 'spec.json'), out, '--format=agent'], {cwd: root, stdio: 'inherit'});
await copyFile(join(here, 'scenes.jsx'), join(out, 'src/scenes.jsx'));
await mkdir(join(out, 'public/photos'), {recursive: true});
await mkdir(join(out, 'public/fonts'), {recursive: true});
for (const [from, to] of [['1.jpg', 'aurora.jpg'], ['3.jpg', 'camp.jpg']]) {
  await copyFile(join(root, 'packages/dev-app/media~', from), join(out, 'public/photos', to));
}
for (const name of ['montserrat.ttf', 'work-sans.ttf']) {
  await copyFile(join(root, 'packages/repurpose/src/Resources/font', name), join(out, 'public/fonts', name));
}
const timeline = JSON.parse(await readFile(join(out, 'timeline.json'), 'utf8'));
const durations = [120, 120, 120, 90, 120];
const transitions = [0, 10, 12, 8, 12];
timeline.slides.forEach((slide, index) => {
  slide.durationInFrames = durations[index];
  slide.transitionInFrames = transitions[index];
});
await writeFile(join(out, 'timeline.json'), JSON.stringify(timeline, null, 2) + '\n');
console.log(JSON.stringify({result: 'done', project: out, next: ['npm install', 'npm run studio', 'npm run render']}));
