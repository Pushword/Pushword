<?php

namespace Pushword\StaticGenerator\Generator;

use Symfony\Component\Filesystem\Filesystem;

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
        $symlink = $this->mustSymlink();

        if (! file_exists($this->getStaticDir().'/download')) {
            $this->filesystem->mkdir($this->getStaticDir().'/download/');
            $this->filesystem->mkdir($this->getStaticDir().'/download/media');
        }

        $dir = dir($this->webDir.'/../media');
        while (false !== $entry = $dir->read()) {
            if ('.' == $entry || '..' == $entry) {
                continue;
            }
            // if the file is an image, it's ever exist (maybe it's slow to check every files)
            if (! file_exists($this->webDir.'/media/default/'.$entry)) {
                if (true === $symlink) {
                    $this->filesystem->symlink(
                        '../../../media/'.$entry,
                        $this->getStaticDir().'/download/media/'.$entry
                    );
                } else {
                    $this->filesystem->copy(
                        $this->webDir.'/../media/'.$entry,
                        $this->getStaticDir().'/download/media/'.$entry
                    );
                }
            }
        }

        //$this->filesystem->$action($this->webDir.'/../media', $this->getStaticDir().'/download/media');
    }
}
