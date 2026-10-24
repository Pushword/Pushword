import React from 'react';
import {AbsoluteFill, Img, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {layerStyle} from './timeline.mjs';

// Reuse this component inside custom scenes to keep the original slide artwork.
export const SlideLayers = ({slide}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  return (
    <AbsoluteFill style={{overflow: 'hidden'}}>
      {slide.layers.map((layer) => (
        <Img key={layer.id} src={staticFile(layer.src)} style={{
          position: 'absolute', width: '100%', height: '100%',
          ...layerStyle(layer.animation, frame, fps, slide.durationInFrames),
        }}/>
      ))}
    </AbsoluteFill>
  );
};

export const Scene = ({slide}) => <SlideLayers slide={slide}/>;
