import {entranceOpacity} from './timeline.mjs';
import {slideLayers} from './scene.mjs';

// HyperFrames drives this seekable timeline for both preview and frame capture.
export function createTimeline(video, root, scenes) {
  const clips = video.slides.map((slide, index) => ({
    slide, element: root.children[index], scene: Object.hasOwn(scenes, slide.id) ? scenes[slide.id] : slideLayers,
  }));
  let time = 0;
  const duration = video.durationInFrames / video.fps;
  const timeline = {
    duration: () => duration,
    time: () => time,
    pause() { return timeline; },
    seek(seconds) {
      time = Math.max(0, Math.min(duration, seconds));
      const frame = Math.min(video.durationInFrames - 1, Math.floor(time * video.fps + 1e-7));
      for (const {slide, element, scene} of clips) {
        const local = frame - slide.from;
        const visible = local >= 0 && local < slide.durationInFrames;
        element.style.visibility = visible ? 'visible' : 'hidden';
        element.style.opacity = entranceOpacity(local, slide.transitionInFrames);
        if (visible) scene.render(element, local, slide, video);
      }
      return timeline;
    },
  };
  return timeline;
}
