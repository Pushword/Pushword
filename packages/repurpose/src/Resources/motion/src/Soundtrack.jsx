import React, {useMemo} from 'react';
import {Audio} from '@remotion/media';
import {useAudioData} from '@remotion/media-utils';
import {staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {duckingEnvelope, trackVolume} from './audio.mjs';

const Music = ({track, gains}) => {
  const frame = useCurrentFrame();
  const {fps, durationInFrames} = useVideoConfig();
  return <Audio name="Music" src={staticFile(track.src)} loop
    volume={trackVolume(track, frame, fps, durationInFrames) * (gains?.[frame] ?? 1)}/>;
};

const NarratedAudio = ({mix}) => {
  const data = useAudioData(staticFile(mix.voice.src));
  const frame = useCurrentFrame();
  const {fps, durationInFrames} = useVideoConfig();
  const gains = useMemo(() => data && mix.music && mix.ducking
    ? duckingEnvelope(data, mix.voice, fps, durationInFrames) : null, [data, mix, fps, durationInFrames]);
  if (!data) return null;
  const voiceFrames = Math.min(durationInFrames, Math.ceil(data.durationInSeconds * fps));
  return <>
    <Audio name="Voice" src={staticFile(mix.voice.src)} volume={trackVolume(mix.voice, frame, fps, voiceFrames)}/>
    {mix.music && <Music track={mix.music} gains={gains}/>}
  </>;
};

export const Soundtrack = ({mix}) => {
  if (mix.voice) return <NarratedAudio mix={mix}/>;
  if (mix.music) return <Music track={mix.music}/>;
  return null;
};
