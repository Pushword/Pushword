import {readFile, access} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import {timeline} from './timeline.mjs';

try {
  const project = JSON.parse(await readFile(new URL('../timeline.json', import.meta.url), 'utf8'));
  const video = timeline(project);
  let assets = 0;
  for (const name of ['voice', 'music']) {
    if (!video.audio[name]) continue;
    await access(fileURLToPath(new URL(`../public/${video.audio[name].src}`, import.meta.url)));
    assets++;
  }
  for (const slide of video.slides) {
    for (const layer of slide.layers) {
      await access(fileURLToPath(new URL(`../public/${layer.src}`, import.meta.url)));
      assets++;
    }
  }
  console.log(JSON.stringify({result: 'passed', slides: video.slides.length, assets,
    durationInFrames: video.durationInFrames, seconds: video.durationInFrames / video.fps}));
} catch (error) {
  console.error(JSON.stringify({result: 'failed', error: error.message}));
  process.exitCode = 1;
}
