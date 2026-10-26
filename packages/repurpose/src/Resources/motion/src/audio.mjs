export function audioMix(raw) {
  if (raw === undefined || raw === null) return {ducking: true};
  if (typeof raw !== 'object' || Array.isArray(raw)) throw new Error('audio must be an object.');
  if (raw.ducking !== undefined && typeof raw.ducking !== 'boolean') throw new Error('audio.ducking must be a boolean.');
  const mix = {ducking: raw.ducking ?? true};
  for (const name of ['voice', 'music']) {
    const track = raw[name];
    if (track === undefined || track === null) continue;
    if (typeof track.src !== 'string' || !/^[a-zA-Z0-9_./-]+$/.test(track.src) ||
        track.src.startsWith('/') || track.src.split('/').includes('..')) {
      throw new Error(`audio.${name}.src must be a relative asset path inside public/.`);
    }
    const normalized = {src: track.src, volume: track.volume ?? (name === 'voice' ? 1 : 0.3),
      fadeIn: track.fadeIn ?? 0, fadeOut: track.fadeOut ?? 0};
    for (const [key, max] of [['volume', 1], ['fadeIn', 30], ['fadeOut', 30]]) {
      if (!Number.isFinite(normalized[key]) || normalized[key] < 0 || normalized[key] > max) {
        throw new Error(`audio.${name}.${key} must be between 0 and ${max}.`);
      }
    }
    mix[name] = normalized;
  }
  return mix;
}

export function trackVolume(track, frame, fps, durationInFrames) {
  if (frame < 0 || frame >= durationInFrames) return 0;
  const fadeIn = track.fadeIn > 0 ? Math.min(1, frame / (track.fadeIn * fps)) : 1;
  const fadeOut = track.fadeOut > 0 ? Math.min(1, (durationInFrames - 1 - frame) / (track.fadeOut * fps)) : 1;
  return track.volume * fadeIn * fadeOut;
}

// Precompute from frame zero: seeking and rendering in parallel use the same gain.
export function duckingEnvelope(data, voice, fps, durationInFrames) {
  const gains = [];
  const voiceFrames = Math.min(durationInFrames, Math.ceil(data.durationInSeconds * fps));
  let gain = 1;
  for (let frame = 0; frame < durationInFrames; frame++) {
    const start = Math.floor(frame * data.sampleRate / fps);
    const end = Math.floor((frame + 1) * data.sampleRate / fps);
    let energy = 0;
    for (const channel of data.channelWaveforms) {
      let sum = 0;
      for (let sample = start; sample < Math.min(end, channel.length); sample++) sum += channel[sample] ** 2;
      energy = Math.max(energy, sum / (end - start));
    }
    const level = Math.sqrt(energy) * trackVolume(voice, frame, fps, voiceFrames);
    const target = level > 0.02 ? (0.02 / level) ** (1 - 1 / 8) : 1;
    const smoothing = Math.exp(-1 / (fps * (target < gain ? 0.05 : 0.4)));
    gain = target + (gain - target) * smoothing;
    gains.push(gain);
  }
  return gains;
}
