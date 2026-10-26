import {execFileSync} from 'node:child_process';
import {mkdir} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';

// Mix once so HyperFrames preview and render play exactly the same soundtrack.
export async function soundtrack(video, directory) {
  const duration = video.durationInFrames / video.fps;
  const inputs = [];
  const filters = [];
  let labels = [];
  for (const name of ['voice', 'music']) {
    const track = video.audio[name];
    if (!track) continue;
    const path = fileURLToPath(new URL(`public/${track.src}`, directory));
    let end = duration;
    if (name === 'voice') {
      const probe = JSON.parse(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'json', path], {encoding: 'utf8'}));
      end = Math.min(duration, Number(probe.format.duration));
    } else inputs.push('-stream_loop', '-1');
    inputs.push('-i', path);
    const chain = [`atrim=duration=${end}`, 'asetpts=PTS-STARTPTS', `volume=${track.volume}`];
    if (Math.round(track.fadeIn * 1000) > 0) chain.push(`afade=t=in:d=${track.fadeIn.toFixed(3)}`);
    if (Math.round(track.fadeOut * 1000) > 0) chain.push('areverse', `afade=t=in:d=${track.fadeOut.toFixed(3)}`, 'areverse');
    chain.push(`apad=whole_dur=${duration}`, `atrim=duration=${duration}`, 'pan=stereo|FL<FL+FC|FR<FR+FC');
    filters.push(`[${labels.length}:a]${chain.join(',')}[${name}]`);
    labels.push(`[${name}]`);
  }
  if (!labels.length) return null;
  if (video.audio.voice && video.audio.music && video.audio.ducking) {
    filters.push('[voice]asplit[voiceMix][sidechain]', '[music][sidechain]sidechaincompress=threshold=0.02:ratio=8:attack=50:release=400[ducked]');
    labels = ['[voiceMix]', '[ducked]'];
  }
  filters.push(`${labels.join('')}amix=inputs=${labels.length}:normalize=0,alimiter=limit=0.95:level=false[mix]`);
  await mkdir(new URL('public/generated/', directory), {recursive: true});
  const output = 'public/generated/soundtrack.wav';
  execFileSync('ffmpeg', ['-v', 'error', '-y', ...inputs, '-filter_complex', filters.join(';'), '-map', '[mix]', '-t', String(duration), '-ar', '48000', '-c:a', 'pcm_s16le', fileURLToPath(new URL(output, directory))], {stdio: ['ignore', 'ignore', 'inherit']});
  return output;
}
