import {layerStyle} from './timeline.mjs';

export const escapeHtml = (value) => String(value).replace(/[&<>"']/g,
  (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));

export const slideLayers = {
  html: (slide) => slide.layers.map((layer) =>
    `<img data-layer="${escapeHtml(layer.id)}" src="../public/${escapeHtml(layer.src)}" alt="" style="position:absolute;inset:0;width:100%;height:100%">`).join('\n'),
  render: (root, frame, slide, video) => {
    for (const [index, image] of root.querySelectorAll('[data-layer]').entries()) {
      const style = layerStyle(slide.layers[index].animation, frame, video.fps, slide.durationInFrames);
      image.style.opacity = style.opacity ?? 1;
      image.style.transform = style.transform ?? 'none';
    }
  },
};
