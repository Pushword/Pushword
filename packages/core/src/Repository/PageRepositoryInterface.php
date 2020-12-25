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

    public function getPublishedPages(string $host = '', array $where = [], array $orderBy = [], int $limit = 0);

    public function getPage($slug, $host, $hostCanBeNull): ?Page;

    public function getIndexablePages(
        $host,
        $hostCanBeNull,
        $locale,
        $defaultLocale,
        ?int $limit = null
    ): QueryBuilder;

    public function getPagesWithoutParent();

    public function setHostCanBeNull($hostCanBeNull);

    public function getPagesUsingMedia($media);

    public function andHost(QueryBuilder $qb, $host, $hostCanBeNull = false): QueryBuilder;

    public function findByHost($host, $hostCanBeNull = false): array;
}
