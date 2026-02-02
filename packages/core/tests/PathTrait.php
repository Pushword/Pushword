<?php

namespace Pushword\Core\Tests;

use Symfony\Component\Filesystem\Filesystem;

trait PathTrait
{
    private string $publicDir = __DIR__.'/../../skeleton/public';

    private string $projectDir = __DIR__.'/../../skeleton';

    private string $publicMediaDir = 'media';

    private function getMediaDir(): string
    {
        $runId = \is_string($_ENV['TEST_RUN_ID'] ?? null) ? $_ENV['TEST_RUN_ID'] : (\is_string($_SERVER['TEST_RUN_ID'] ?? null) ? $_SERVER['TEST_RUN_ID'] : '');
        if ('' !== $runId) {
            return sys_get_temp_dir().'/com.github.pushword.pushword/tests/'.$runId.'/media';
        }

        return __DIR__.'/../../skeleton/media';
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
