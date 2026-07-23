<?php

namespace Pushword\Repurpose\Service;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Rasterises an SVG to PNG through headless Chromium — the same engine the
 * studio's in-browser export uses, so the pixels match what a human would see.
 * Deliberately the only backend: Imagick's SVG path ignores embedded fonts,
 * and a wrong preview is worse for an agent than an honest "not available"
 * (the SVG endpoints keep working without it).
 */
final class ChromiumRasterizer
{
    private const array CANDIDATES = ['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable', 'chrome'];

    private string|false|null $binary = null;

    public function __construct(
        private readonly ?string $chromiumBinary = null,
        private readonly float $timeout = 60,
    ) {
    }

    public function available(): bool
    {
        return null !== $this->binary();
    }

    /**
     * The PNG bytes, or null when no Chromium binary is available or the
     * screenshot failed.
     */
    public function rasterize(string $svg, int $width, int $height): ?string
    {
        $binary = $this->binary();
        if (null === $binary) {
            return null;
        }

        $dir = sys_get_temp_dir().'/pw-repurpose-'.bin2hex(random_bytes(6));
        if (! mkdir($dir, 0o700)) {
            return null;
        }

        $svgPath = $dir.'/sheet.svg';
        $pngPath = $dir.'/sheet.png';
        file_put_contents($svgPath, $svg);

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--hide-scrollbars',
                '--force-device-scale-factor=1',
                '--user-data-dir='.$dir.'/profile',
                '--window-size='.$width.','.$height,
                '--screenshot='.$pngPath,
                'file://'.$svgPath,
            ], timeout: $this->timeout);
            $process->run();

            $png = @file_get_contents($pngPath);

            return false === $png || '' === $png ? null : $png;
        } catch (ProcessException) {
            // A Chromium that hangs past the timeout (or cannot be started at all)
            // throws instead of exiting — seen on CI runners. The caller documents a
            // 501 pointing back at the per-slide SVGs for "no preview available", so
            // a stuck browser must degrade to that, never surface as a 500.
            return null;
        } finally {
            $this->cleanup($dir);
        }
    }

    private function binary(): ?string
    {
        if (null === $this->binary) {
            $this->binary = $this->chromiumBinary
                ?? $this->autodetect()
                ?? false;
        }

        return false === $this->binary ? null : $this->binary;
    }

    private function autodetect(): ?string
    {
        $finder = new ExecutableFinder();
        foreach (self::CANDIDATES as $candidate) {
            $path = $finder->find($candidate);
            if (null !== $path) {
                return $path;
            }
        }

        return null;
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
