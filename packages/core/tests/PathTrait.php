<?php

namespace Pushword\Core\Tests;

use Symfony\Component\Filesystem\Filesystem;

trait PathTrait
{
    private string $publicDir = __DIR__.'/../../dev-app/public';

    private string $projectDir = __DIR__.'/../../dev-app';

    private string $publicMediaDir = 'media';

    /**
     * Both dirs are per worker, and the bootstrap is the one that decides where:
     * read what it exported rather than rebuilding its layout here. The fallbacks
     * are the defaults it uses when a run has no id.
     */
    private function testDir(string $variable, string $default): string
    {
        $dir = getenv($variable);

        return \is_string($dir) && '' !== $dir ? $dir : $default;
    }

    private function getMediaDir(): string
    {
        return $this->testDir('PUSHWORD_TEST_MEDIA_DIR', $this->projectDir.'/media');
    }

    /** Where derivatives are written, so nothing lands in the dev-app's own public/media. */
    private function getMediaCacheDir(): string
    {
        return $this->testDir('PUSHWORD_TEST_MEDIA_CACHE_DIR', $this->publicDir.'/'.$this->publicMediaDir);
    }

    protected function ensureMediaFileExists(string $fileName = 'piedweb-logo.png'): void
    {
        $mediaDir = $this->getMediaDir();
        $backupFile = $this->projectDir.'/media~/'.$fileName;

        if (! file_exists($mediaDir.'/'.$fileName) && file_exists($backupFile)) {
            new Filesystem()->copy($backupFile, $mediaDir.'/'.$fileName);
        }
    }
}
