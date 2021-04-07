<?php

namespace Pushword\Core\Entity;

trait MediaLoaderTrait
{
    /**
     * Useed in twig AppExtension transformStringToMedia.
     * Convert the source path (often /media/default/... or just ...) in a Media Object.
     */
    public static function loadFromSrc(string $src): MediaInterface
    {
        $src = false !== strpos($src, '/') ? substr($src, \strlen('/media/default/')) : $src;

        /** @var MediaInterface $media */
        $media = new self();
        $media->setMedia($src);

        return $media;
    }
}
