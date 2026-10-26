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
