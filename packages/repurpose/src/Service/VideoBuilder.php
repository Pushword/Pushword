<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use FilesystemIterator;
use Pushword\Repurpose\Model\AudioMix;
use Pushword\Repurpose\Model\AudioTrack;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Encodes the browser-rasterised slide PNGs into a single MP4 slideshow through
 * ffmpeg — one still frame per slide, each held for a fixed duration, at the
 * output format's pixel size.
 *
 * ffmpeg is the site's own dependency: when no binary is found the studio degrades
 * to an honest "install ffmpeg" warning rather than shipping a broken file, exactly
 * as {@see ChromiumRasterizer} does for previews. The .zip and .svg exports never
 * need it.
 */
final class VideoBuilder
{
    /** How long each slide is held on screen, in seconds. */
    private const float SECONDS_PER_SLIDE = 3.0;

    private string|false|null $binary = null;

    public function __construct(
        private readonly AudioAssetResolver $audioAssets,
        private readonly ?string $ffmpegBinary = null,
        private readonly float $timeout = 120,
    ) {
    }

    public function available(): bool
    {
        return null !== $this->binary();
    }

    /**
     * The MP4 bytes for a slideshow of these PNGs at the given pixel size.
     *
     * @param list<string> $pngs raw PNG bytes, in slide order
     *
     * @throws AudioAssetException when an audio track's media file is unusable
     * @throws RuntimeException    when ffmpeg is unavailable or the encode failed —
     *                             the caller turns this into a user-facing warning
     */
    public function build(array $pngs, int $width, int $height, ?AudioMix $audio = null): string
    {
        $binary = $this->binary();
        if (null === $binary) {
            throw new RuntimeException('ffmpeg is not available.');
        }

        if ([] === $pngs) {
            throw new RuntimeException('No slides to encode.');
        }

        $dir = sys_get_temp_dir().'/pw-repurpose-video-'.bin2hex(random_bytes(6));
        if (! mkdir($dir, 0o700)) {
            throw new RuntimeException('Could not create the encode workspace.');
        }

        try {
            $listFile = $this->writeFrames($dir, $pngs);
            $outFile = $dir.'/carousel.mp4';

            $audioArguments = $this->audioArguments($dir, $audio, \count($pngs) * self::SECONDS_PER_SLIDE);
            $command = $this->command($binary, $listFile, $outFile, $width, $height, $audioArguments);
            $process = new Process($command, timeout: $this->timeout);
            $process->run();

            $mp4 = @file_get_contents($outFile);
            if (! $process->isSuccessful() || false === $mp4 || '' === $mp4) {
                throw new RuntimeException('ffmpeg could not encode the video.');
            }

            return $mp4;
        } catch (ProcessException $processException) {
            // A binary that hangs past the timeout (or cannot be started at all)
            // throws instead of exiting — seen on CI runners.
            throw new RuntimeException('ffmpeg could not be started.', 0, $processException);
        } finally {
            $this->cleanup($dir);
        }
    }

    /** @return list<string> */
    private function audioArguments(string $dir, ?AudioMix $audio, float $duration): array
    {
        $arguments = [];
        $filters = [];
        $labels = [];
        foreach (['voice', 'music'] as $name) {
            $track = $audio?->{$name};
            if (! $track instanceof AudioTrack) {
                continue;
            }

            $path = $dir.'/'.$name.'.'.pathinfo($track->media, \PATHINFO_EXTENSION);
            $stream = $this->audioAssets->stream($name, $track);
            file_put_contents($path, $stream);
            fclose($stream);
            if ('music' === $name) {
                $arguments[] = '-stream_loop';
                $arguments[] = '-1';
            }

            $arguments[] = '-i';
            $arguments[] = $path;
            $input = \count($labels) + 1;
            $filters[] = '['.$input.':a]'.$this->trackFilters($track, $duration).'['.$name.']';
            $labels[] = '['.$name.']';
        }

        if ([] === $labels) {
            return [];
        }

        if ($audio?->voice instanceof AudioTrack && $audio->music instanceof AudioTrack && $audio->ducking) {
            $filters[] = '[voice]asplit[voiceMix][sidechain]';
            $filters[] = '[music][sidechain]sidechaincompress=threshold=0.02:ratio=8:attack=50:release=400[ducked]';
            $labels = ['[voiceMix]', '[ducked]'];
        }

        $filters[] = implode('', $labels).'amix=inputs='.\count($labels).':normalize=0,alimiter=limit=0.95:level=false[mix]';
        $arguments[] = '-filter_complex';
        $arguments[] = implode(';', $filters);
        $arguments[] = '-map';
        $arguments[] = '0:v';
        $arguments[] = '-map';
        $arguments[] = '[mix]';
        $arguments[] = '-c:a';
        $arguments[] = 'aac';
        $arguments[] = '-t';
        $arguments[] = $this->decimal($duration);

        return $arguments;
    }

    private function trackFilters(AudioTrack $track, float $duration): string
    {
        $seconds = $this->decimal($duration);
        $filters = ['atrim=duration='.$seconds, 'asetpts=PTS-STARTPTS', 'volume='.$this->decimal($track->volume)];
        // A fade that rounds to zero is skipped: afade reads d=0 as unset and
        // falls back to a 44100-sample fade.
        if (round($track->fadeIn, 3) > 0) {
            $filters[] = 'afade=t=in:d='.$this->decimal($track->fadeIn);
        }

        if (round($track->fadeOut, 3) > 0) {
            // Reverse after trimming so a short narration fades at its own end.
            $filters[] = 'areverse';
            $filters[] = 'afade=t=in:d='.$this->decimal($track->fadeOut);
            $filters[] = 'areverse';
        }

        $filters[] = 'apad=whole_dur='.$seconds;
        $filters[] = 'atrim=duration='.$seconds;
        // Every track enters the mix as stereo, so a mono voice cannot fold the
        // music into mono; a mono source plays on both sides at its own level.
        $filters[] = 'pan=stereo|FL<FL+FC|FR<FR+FC';

        return implode(',', $filters);
    }

    /**
     * A filter argument in plain decimals: PHP prints 0.00001 as 1.0E-5, which
     * ffmpeg rejects as a duration.
     */
    private function decimal(float $value): string
    {
        return \sprintf('%.3F', $value);
    }

    /**
     * Write each slide as `slide-000.png` and a concat-demuxer playlist holding
     * each for {@see self::SECONDS_PER_SLIDE} — including the last one, whose
     * duration ffmpeg honours, so every slide shows for the same time.
     *
     * @param list<string> $pngs
     */
    private function writeFrames(string $dir, array $pngs): string
    {
        $lines = [];
        foreach ($pngs as $i => $png) {
            $name = \sprintf('slide-%03d.png', $i);
            file_put_contents($dir.'/'.$name, $png);
            $lines[] = "file '".$name."'";
            $lines[] = 'duration '.self::SECONDS_PER_SLIDE;
        }

        $listFile = $dir.'/frames.txt';
        file_put_contents($listFile, implode("\n", $lines)."\n");

        return $listFile;
    }

    /**
     * @param list<string> $audioArguments
     *
     * @return list<string>
     */
    private function command(string $binary, string $listFile, string $outFile, int $width, int $height, array $audioArguments): array
    {
        return [
            $binary,
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $listFile,
            ...$audioArguments,
            // Fit each frame into the target box (letterboxing anything off-ratio),
            // then force yuv420p on even dimensions — what every player and social
            // upload expects.
            '-vf', 'scale='.$width.':'.$height.':force_original_aspect_ratio=decrease,pad='.$width.':'.$height.':(ow-iw)/2:(oh-ih)/2,format=yuv420p',
            '-r', '30',
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
            $outFile,
        ];
    }

    private function binary(): ?string
    {
        if (null === $this->binary) {
            // A configured absolute path is used as-is when it is executable;
            // otherwise it (or the 'ffmpeg' default) is resolved through PATH.
            // Anything that resolves to nothing means "no ffmpeg" — the studio
            // then shows its install hint rather than a broken export.
            $candidate = $this->ffmpegBinary ?? 'ffmpeg';
            $this->binary = is_file($candidate) && is_executable($candidate)
                ? $candidate
                : (new ExecutableFinder()->find($candidate) ?? false);
        }

        return false === $this->binary ? null : $this->binary;
    }

    private function cleanup(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($dir);
    }
}
