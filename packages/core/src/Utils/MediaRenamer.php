<?php

namespace Pushword\Core\Utils;

use Pushword\Core\Entity\Media;

class MediaRenamer
{
    private int $iterate = 1;

    public function reset(): void
    {
        $this->iterate = 1;
    }

    public function rename(Media $media): Media
    {
        $newName = (1 === $this->iterate ? $media->getAlt()
            : preg_replace('/ \(\d+\)$/', '', $media->getAlt()));
        $newName .= ' ('.($this->iterate + 1).')';

        $media->setAlt($newName);
        $media->setSlug($newName);

        ++$this->iterate;

        return $media;
    }

    public function getIteration(): int
    {
        return $this->iterate;
    }
}
