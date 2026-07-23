<?php

namespace Pushword\Repurpose\Service;

use FilesystemIterator;
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
     * @throws RuntimeException when ffmpeg is unavailable or the encode failed —
     *                          the caller turns this into a user-facing warning
     */
    public function build(array $pngs, int $width, int $height): string
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

            $process = new Process($this->command($binary, $listFile, $outFile, $width, $height), timeout: $this->timeout);
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
     * @return list<string>
     */
    private function command(string $binary, string $listFile, string $outFile, int $width, int $height): array
    {
        return [
            $binary,
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $listFile,
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
