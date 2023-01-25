<?php

namespace Pushword\Core\Utils;

use Pushword\Core\Entity\MediaInterface;

class MediaRenamer
{
    private int $iterate = 1;

    public function reset(): void
    {
        $this->iterate = 1;
    }

    public function rename(MediaInterface $media): MediaInterface
    {
        $newName = (1 === $this->iterate ? $media->getName()
            : F::preg_replace_str('/ \([0-9]+\)$/', '', $media->getName()));
        $newName .= ' ('.($this->iterate + 1).')';

        $media->setName($newName);
        $media->setSlug($newName);

        ++$this->iterate;

        return $media;
    }

    public function getIteration(): int
    {
        return $this->iterate;
    }
}
