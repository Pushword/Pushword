<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\FontPairing;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Downloads missing Google Fonts faces into `repurpose.font_dir`, which then
 * serves as their cache: a face on disk is never fetched again. The studio and
 * the API call it before validating a spec; rendering never does, so a render
 * makes no network call.
 */
final readonly class FontInstaller
{
    public function __construct(
        private FontResolver $fonts,
        private GoogleFontDownloader $downloader,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * Installs whatever the pairing still misses. A custom family with a
     * malformed name is left to the validator rather than sent to Google.
     */
    public function installPairing(string|FontPairing|null $pairing): void
    {
        foreach ($this->fonts->faces($pairing) as $face) {
            if (1 === preg_match(FontPairing::FAMILY, $face['family'])) {
                $this->install($face['family'], $face['weight']);
            }
        }
    }

    /**
     * True when the face is on disk, already or after this download.
     */
    public function install(string $family, int $weight): bool
    {
        if ($this->fonts->hasFace($family, $weight)) {
            return true;
        }

        $ttf = $this->downloader->download($family, $weight);
        if (null === $ttf) {
            return false;
        }

        // Atomic, so a concurrent render never measures a half-written file.
        $this->filesystem->dumpFile($this->fonts->installDir().'/'.$this->fonts->fileNameFor($family, $weight), $ttf);

        return true;
    }
}
