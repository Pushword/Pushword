import {copyFile, cp, mkdir, readFile, writeFile} from 'node:fs/promises';
import {resolve, join} from 'node:path';

if (!process.argv[2]) throw new Error('Usage: node prepare.mjs <new-project-directory>');
const out = resolve(process.argv[2]);
// Refuse existing directories, including empty ones, to preserve authored work.
await mkdir(out);
await cp(new URL('../../src/Resources/motion/', import.meta.url), out, {recursive: true});
await copyFile(new URL('scenes.mjs', import.meta.url), join(out, 'src/scenes.mjs'));
await copyFile(new URL('spec.json', import.meta.url), join(out, 'spec.json'));
await copyFile(new URL('README.md', import.meta.url), join(out, 'EXAMPLE.md'));
await cp(new URL('photos/', import.meta.url), join(out, 'public/photos'), {recursive: true});
await mkdir(join(out, 'public/fonts'));
for (const name of ['montserrat.ttf', 'work-sans.ttf']) {
  await copyFile(new URL(`../../src/Resources/font/${name}`, import.meta.url), join(out, 'public/fonts', name));
}
const timings = [[120, 0], [120, 10], [120, 12], [90, 8], [120, 12]];
const timeline = {
  $schema: './timeline.schema.json', width: 1080, height: 1920, fps: 30,
  slides: timings.map(([durationInFrames, transitionInFrames], index) => ({
    id: `slide-${index + 1}`, durationInFrames, transitionInFrames, layers: [],
  })),
};
await writeFile(join(out, 'timeline.json'), JSON.stringify(timeline, null, 2) + '\n');
const spec = JSON.parse(await readFile(new URL('spec.json', import.meta.url), 'utf8'));
await writeFile(join(out, 'caption.txt'), `${spec.caption}\n\n${spec.hashtags.map((tag) => `#${tag}`).join(' ')}\n`);
console.log(JSON.stringify({result: 'done', project: out, readme: 'EXAMPLE.md',
  next: ['npm install', 'npm run check', 'npm run still -- --frame=205', 'npm run studio', 'npm run render']}));
