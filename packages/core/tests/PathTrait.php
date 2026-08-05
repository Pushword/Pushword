<?php

namespace Pushword\Core\Tests;

use Symfony\Component\Filesystem\Filesystem;

trait PathTrait
{
    private string $publicDir = __DIR__.'/../../dev-app/public';

    private string $projectDir = __DIR__.'/../../dev-app';

    private string $publicMediaDir = 'media';

    private function getMediaDir(): string
    {
        $runId = \is_string($_ENV['TEST_RUN_ID'] ?? null) ? $_ENV['TEST_RUN_ID'] : (\is_string($_SERVER['TEST_RUN_ID'] ?? null) ? $_SERVER['TEST_RUN_ID'] : '');
        if ('' !== $runId) {
            return sys_get_temp_dir().'/com.github.pushword.pushword/tests/'.$runId.'/media';
        }

        return __DIR__.'/../../dev-app/media';
    }

    /**
     * Where derivatives are written — per worker under the run dir, so nothing lands
     * in the dev-app's own public/media. The bootstrap exports it; falling back to
     * the default layout keeps a kernel-less unit test working.
     */
    private function getMediaCacheDir(): string
    {
        $dir = getenv('PUSHWORD_TEST_MEDIA_CACHE_DIR');

        return \is_string($dir) && '' !== $dir ? $dir : $this->publicDir.'/'.$this->publicMediaDir;
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
