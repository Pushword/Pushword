import React from 'react';
import {AbsoluteFill, Composition, registerRoot, Sequence, useCurrentFrame} from 'remotion';
import project from '../timeline.json';
import {Scene} from './Scene';
import {scenes} from './scenes';
import {entranceOpacity, timeline} from './timeline.mjs';

const video = timeline(project);

const Clip = ({slide}) => {
  const frame = useCurrentFrame();
  const Component = Object.hasOwn(scenes, slide.id) ? scenes[slide.id] : Scene;
  return (
    <AbsoluteFill style={{opacity: entranceOpacity(frame, slide.transitionInFrames), overflow: 'hidden'}}>
      <Component slide={slide}/>
    </AbsoluteFill>
  );
};

const Video = () => (
  <AbsoluteFill style={{backgroundColor: '#0b1120'}}>
    {video.slides.map((slide) => (
      <Sequence key={slide.id} name={slide.id} from={slide.from} durationInFrames={slide.durationInFrames}>
        <Clip slide={slide}/>
      </Sequence>
    ))}
  </AbsoluteFill>
);

const Root = () => <Composition id="Repurpose" component={Video} width={video.width}
  height={video.height} fps={video.fps} durationInFrames={video.durationInFrames}/>;

registerRoot(Root);
