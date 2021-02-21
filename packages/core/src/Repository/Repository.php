<?php

namespace Pushword\Core\Repository;

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
