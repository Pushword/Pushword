<?php

namespace Pushword\Core\Entity\MediaTrait;

use Pushword\Core\Entity\MediaInterface;

trait MediaLoaderTrait
{
    /**
     * Useed in twig AppExtension transformStringToMedia.
     * Convert the source path (often /media/default/... or just ...) in a Media Object.
     */
    public static function loadFromSrc(string $src): MediaInterface
    {
        // TODO : move it to a separate service to get it from an SQL and get all properties

        $src = str_contains($src, '/') ? \Safe\substr($src, \strlen('/media/default/')) : $src;

        /** @var MediaInterface $self */
        $self = new self(); // @phpstan-ignore-line
        $self->setMedia($src);

        return $self;
    }
}
