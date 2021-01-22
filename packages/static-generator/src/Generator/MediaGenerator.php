<?php

namespace Pushword\StaticGenerator\Generator;

class MediaGenerator extends AbstractGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->copyMediaToDownload();
    }

    /**
     * Copy or Symlink "not image" media to download folder.
     *
     * @return void
     */
    protected function copyMediaToDownload()
    {
        $publicMediaDir = $this->params->get('pw.public_media_dir');
        $mediaDir = $this->params->get('pw.media_dir');
        $staticMediaDir = $this->getStaticDir().'/'.$publicMediaDir;

        $symlink = $this->mustSymlink();

        if (! file_exists($staticMediaDir)) {
            $this->filesystem->mkdir($staticMediaDir);
        }

        $dir = dir($mediaDir);
        while (false !== $entry = $dir->read()) {
            if ('.' == $entry || '..' == $entry) {
                continue;
            }

            if (true === $symlink) {
                $this->filesystem->symlink($mediaDir.'/'.$entry, $staticMediaDir.'/'.$entry);

                continue;
            }

            $this->filesystem->copy($mediaDir.'/'.$entry, $staticMediaDir.'/'.$entry);
        }
    }
}
