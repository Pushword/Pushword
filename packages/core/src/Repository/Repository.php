<?php

namespace Pushword\Core\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageInterface;

class Repository
{
    /**
     * @param class-string<PageInterface> $pageEntity
     * @template T as PageInterface
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public static function getPageRepository(EntityManagerInterface|ManagerRegistry $doctrine, string $pageEntity): PageRepository // @phpstan-ignore-line
    {
        return $doctrine->getRepository($pageEntity); // @phpstan-ignore-line
    }

    /**
     * @param class-string<MediaInterface> $mediaEntity
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public static function getMediaRepository(EntityManagerInterface|ManagerRegistry $doctrine, string $mediaEntity): MediaRepository
    {
        return $doctrine->getRepository($mediaEntity); // @phpstan-ignore-line
    }
}
