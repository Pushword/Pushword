<?php

namespace Pushword\StaticGenerator\Generator;

class MediaGenerator extends AbstractGenerator
{
    public function generate(string $host = null): void
    {
        parent::generate($host);

        $this->copyMediaToDownload();
    }

    /**
     * Copy or Symlink "not image" media to download folder.
     *
     * @psalm-suppress RedundantCast
     */
    protected function copyMediaToDownload(): void
    {
        $publicMediaDir = $this->params->get('pw.public_media_dir');
        $mediaDir = $this->params->get('pw.media_dir');
        $staticMediaDir = $this->getStaticDir().'/'.$publicMediaDir;

        $symlink = $this->mustSymlink();

        // TODO : fix when media symlink exist and then, we want to copy
        if (! file_exists($staticMediaDir)) {
            $this->filesystem->mkdir($staticMediaDir);
        }

        $dir = dir($mediaDir);
        if (false === $dir) {
            return;
        }

        while (false !== $entry = $dir->read()) {
            if ('.' == $entry) {
                continue;
            }

            if ('..' == $entry) {
                continue;
            }

            if ($symlink) {
                $this->filesystem->symlink($mediaDir.'/'.$entry, $staticMediaDir.'/'.$entry);

                continue;
            }

            $this->filesystem->copy($mediaDir.'/'.$entry, $staticMediaDir.'/'.$entry);
        }
    }
}
