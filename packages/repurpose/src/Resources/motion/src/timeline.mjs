const animations = ['none', 'fade', 'rise', 'zoom-in'];

function integer(value, path, minimum = 0) {
  if (!Number.isSafeInteger(value) || value < minimum) {
    throw new Error(`${path} must be an integer >= ${minimum}.`);
  }
}

// All timing is expressed in frames; random access renders the same frame as playback.
export function timeline(project) {
  for (const dimension of ['width', 'height']) {
    integer(project[dimension], dimension, 2);
    if (project[dimension] % 2 !== 0) throw new Error(`${dimension} must be even for H.264.`);
  }
  integer(project.fps, 'fps', 1);
  if (project.fps > 60) throw new Error('fps must be <= 60.');
  if (!Array.isArray(project.slides) || project.slides.length === 0) {
    throw new Error('slides must be a non-empty array.');
  }

  let end = 0;
  const ids = new Set();
  const slides = project.slides.map((slide, index) => {
    const path = `slides[${index}]`;
    if (typeof slide.id !== 'string' || !slide.id || ids.has(slide.id)) {
      throw new Error(`${path}.id must be a unique, non-empty string.`);
    }
    ids.add(slide.id);
    integer(slide.durationInFrames, `${path}.durationInFrames`, 1);
    integer(slide.transitionInFrames, `${path}.transitionInFrames`);
    if (index === 0 && slide.transitionInFrames !== 0) {
      throw new Error(`${path}.transitionInFrames must be 0 on the first slide.`);
    }
    const previous = project.slides[index - 1];
    if (slide.transitionInFrames >= slide.durationInFrames || (previous &&
        slide.transitionInFrames + previous.transitionInFrames >= previous.durationInFrames)) {
      throw new Error(`${path}.transitionInFrames leaves no hold time between transitions.`);
    }
    if (!Array.isArray(slide.layers)) throw new Error(`${path}.layers must be an array.`);
    const layerIds = new Set();
    for (const [layerIndex, layer] of slide.layers.entries()) {
      const layerPath = `${path}.layers[${layerIndex}]`;
      if (typeof layer.id !== 'string' || !layer.id || layerIds.has(layer.id)) {
        throw new Error(`${layerPath}.id must be a unique, non-empty string.`);
      }
      layerIds.add(layer.id);
      if (typeof layer.src !== 'string' || !/^[a-zA-Z0-9_./-]+$/.test(layer.src) ||
          layer.src.startsWith('/') || layer.src.split('/').includes('..')) {
        throw new Error(`${layerPath}.src must be a relative asset path inside public/.`);
      }
      if (!animations.includes(layer.animation)) throw new Error(`${layerPath}.animation is unknown.`);
    }
    const from = end - slide.transitionInFrames;
    end = from + slide.durationInFrames;
    integer(end, `${path} end frame`, 1);
    return {...slide, from};
  });
  return {width: project.width, height: project.height, fps: project.fps, slides, durationInFrames: end};
}

export function entranceOpacity(frame, transitionInFrames) {
  return transitionInFrames < 2 ? 1 : Math.max(0, Math.min(1, frame / (transitionInFrames - 1)));
}

export function layerStyle(animation, frame, fps, durationInFrames) {
  const entranceFrames = Math.min(durationInFrames, Math.round(fps * 0.5));
  const progress = entranceFrames < 2 ? 1 : Math.max(0, Math.min(1, frame / (entranceFrames - 1)));
  const eased = 1 - (1 - progress) ** 3;
  switch (animation) {
    case 'fade': return {opacity: eased};
    case 'rise': return {opacity: eased, transform: `translateY(${4 * (1 - eased)}%)`};
    case 'zoom-in': {
      const zoom = 1 + 0.04 * Math.max(0, Math.min(1, frame / Math.max(1, durationInFrames - 1)));
      return {transform: `scale(${zoom})`};
    }
    default: return {};
  }
}
