import {mkdir, readFile, readdir, unlink, writeFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import {timeline} from './timeline.mjs';
import {escapeHtml, slideLayers} from './scene.mjs';
import {scenes} from './scenes.mjs';
import {soundtrack} from './soundtrack.mjs';

export async function build() {
  const project = JSON.parse(await readFile(new URL('../timeline.json', import.meta.url), 'utf8'));
  const video = timeline(project);
  const duration = video.durationInFrames / video.fps;
  await mkdir(new URL('../compositions/', import.meta.url), {recursive: true});
  const clips = await Promise.all(video.slides.map(async (slide, index) => {
    const scene = Object.hasOwn(scenes, slide.id) ? scenes[slide.id] : slideLayers;
    await writeFile(new URL(`../compositions/scene-${index}.html`, import.meta.url),
      `<div data-composition-id="scene-${index}" data-no-timeline data-duration="${slide.durationInFrames / video.fps}" data-width="${video.width}" data-height="${video.height}">${scene.html(slide, video)}</div>`);
    return `<section data-composition-id="scene-${index}" data-composition-src="compositions/scene-${index}.html" data-no-timeline id="${escapeHtml(slide.id)}" class="clip" data-start="${slide.from / video.fps}" data-duration="${slide.durationInFrames / video.fps}" data-track-index="${index}" style="position:absolute;inset:0;overflow:hidden;visibility:hidden"></section>`;
  }));
  for (const name of await readdir(new URL('../compositions/', import.meta.url))) {
    const match = name.match(/^scene-(\d+)\.html$/);
    if (match && Number(match[1]) >= video.slides.length) {
      await unlink(new URL(`../compositions/${name}`, import.meta.url));
    }
  }
  const audio = await soundtrack(video, new URL('../', import.meta.url));
  await mkdir(new URL('../out/', import.meta.url), {recursive: true});
  await writeFile(new URL('../index.html', import.meta.url), `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=${video.width}, height=${video.height}">
  <title>Pushword motion</title>
  <style>*{box-sizing:border-box}body{margin:0}#Repurpose{position:relative;width:${video.width}px;height:${video.height}px;overflow:hidden;background:#0b1120}</style>
</head>
<body>
  <main id="Repurpose" data-composition-id="Repurpose" data-start="0" data-duration="${duration}" data-width="${video.width}" data-height="${video.height}" data-fps="${video.fps}">
    ${clips.join('\n')}
    ${audio ? `<audio id="soundtrack" src="${audio}" data-start="0" data-duration="${duration}"></audio>` : ''}
  </main>
  <script id="project" type="application/json">${JSON.stringify(video).replace(/</g, '\\u003c')}</script>
  <script type="module">
    import {createTimeline} from './src/player.mjs';
    import {scenes} from './src/scenes.mjs';
    const video = JSON.parse(document.getElementById('project').textContent);
    const player = createTimeline(video, document.getElementById('Repurpose'), scenes);
    player.seek(0);
    window.__timelines = window.__timelines || {};
    window.__timelines.Repurpose = player;
  </script>
</body>
</html>
`);
  return video;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) await build();
