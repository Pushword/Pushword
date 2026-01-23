<?php

namespace Pushword\Core\Tests;

use Symfony\Component\Filesystem\Filesystem;

trait PathTrait
{
    private string $publicDir = __DIR__.'/../../skeleton/public';

    private string $projectDir = __DIR__.'/../../skeleton';

    private string $publicMediaDir = 'media';

    private string $mediaDir = __DIR__.'/../../skeleton/media';

    protected function ensureMediaFileExists(string $fileName = 'piedweb-logo.png'): void
    {
        $mediaFile = $this->mediaDir.'/'.$fileName;
        $backupFile = $this->projectDir.'/media~/'.$fileName;

        if (! file_exists($mediaFile) && file_exists($backupFile)) {
            $fs = new Filesystem();
            $fs->copy($backupFile, $mediaFile);
        }
    }
}
