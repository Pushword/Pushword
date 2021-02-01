<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\PageInterface as Page;

interface PageRepositoryInterface extends ServiceEntityRepositoryInterface, ObjectRepository, Selectable
{
    public function createQueryBuilder($alias, $indexBy = null);

    /**
     * Can be used via a twig function.
     *
     * @param string|array $host
     * @param array        $orderBy containing key,direction
     * @param int|array    $limit   containing start,max or just max
     */
    public function getPublishedPages($host = '', array $where = [], array $orderBy = [], $limit = 0);

    public function getPublishedPageQueryBuilder($host = '', array $where = [], array $orderBy = [], int $limit = 0): QueryBuilder;

    public function getPage(string $slug, string $host): ?Page;

    public function getIndexablePagesQuery(
        string $host,
        string $locale,
        ?int $limit = null
    ): QueryBuilder;

    public function getPagesWithoutParent(): array;

    public function getPagesUsingMedia(string $media): array;

    public function andHost(QueryBuilder $qb, $host): QueryBuilder;

    public function findByHost(string $host): array;
}
