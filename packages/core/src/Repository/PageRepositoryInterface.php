<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageInterface;

/**
 * @extends Selectable<int, PageInterface>
 * @extends ObjectRepository<PageInterface>
 */
interface PageRepositoryInterface extends ServiceEntityRepositoryInterface, ObjectRepository, Selectable
{
    /**
     * Creates a new QueryBuilder instance that is prepopulated for this entity name.
     *
     * @param string $alias
     * @param string $indexBy the index for the from
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder($alias, $indexBy = null);

    /**
     * Can be used via a twig function.
     *
     * @param string|array<string> $host
     * @param array<(string|int), string> $orderBy
     * @param array<mixed> $where
     * @param int|array<(string|int), int> $limit
     *
     * @return PageInterface[]
     */
    public function getPublishedPages(
        array|string $host = '',
        array $where = [],
        array $orderBy = [],
        array|int $limit = 0,
        bool $withRedirection = true
    );

    /**
     * Can be used via a twig function.
     *
     * @param string|array<string> $host
     * @param array<(string|int), string> $orderBy
     * @param array<mixed> $where
     * @param int|array<(string|int), int> $limit
     */
    public function getPublishedPageQueryBuilder(array|string $host = '', array $where = [], array $orderBy = [], array|int $limit = 0): QueryBuilder;

    /**
     * @param string|string[] $host
     */
    public function getPage(string $slug, array|string $host, bool $checkId = true): ?PageInterface;

    public function getIndexablePagesQuery(
        string $host,
        string $locale,
        ?int $limit = null
    ): QueryBuilder;

    /**
     * @return PageInterface[]
     */
    public function getPagesWithoutParent(): array;

    /**
     * @return PageInterface[]
     */
    public function getPagesUsingMedia(MediaInterface $media): array;

    /**
     * @param string|string[] $host
     */
    public function andHost(QueryBuilder $queryBuilder, array|string $host): QueryBuilder;

    /**
     * @param string|string[] $host
     *
     * @return PageInterface[]
     */
    public function findByHost(array|string $host): array;
}
