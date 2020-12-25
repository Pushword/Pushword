<?php

namespace Pushword\Core\Repository;

/**
 * todo implement interface when needed
 * Useful for avoiding intelephense error.
 */
class Repository
{
    public static function getPageRepository($doctrine, string $pageEntity): PageRepositoryInterface
    {
        return $doctrine->getRepository($pageEntity);
    }

    public static function getMediaRepository($doctrine, string $pageEntity): MediaRepository
    {
        return $doctrine->getRepository($pageEntity);
    }
}
