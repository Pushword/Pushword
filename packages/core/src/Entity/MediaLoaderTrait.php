<?php

namespace Pushword\Core\Entity;

trait MediaLoaderTrait
{
    /**
     * Useed in twig AppExtension transformStringToMedia.
     * Convert the source path (often /media/default/... or just ...) in a Media Object.
     */
    public static function loadFromSrc(string $src): self
    {
        $src = false !== strpos($src, '/') ? substr($src, \strlen('/media/default/')) : $src;

        $media = new self();
        $media->setMedia($src);

        return $media;
    }
}
