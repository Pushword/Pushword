<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Pushword\Core\Entity\PageInterface;

/**
 * @method PageInterface|null                        find($id, $lockMode = null, $lockVersion = null)
 * @method PageInterface|null                        findOneBy(array $criteria, array $orderBy = null)
 * @method list<\Pushword\Core\Entity\PageInterface> findAll()
 * @method list<\Pushword\Core\Entity\PageInterface> findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends ServiceEntityRepository implements PageRepositoryInterface
{
    protected $hostCanBeNull = false;

    public function getPublishedPages($host = '', array $where = [], array $orderBy = [], $limit = 0, bool $withRedirection = true)
    {
        $qb = $this->getPublishedPageQueryBuilder($host, $where, $orderBy);

        if (! $withRedirection) {
            $this->andNotRedirection($qb);
        }

        $this->limit($qb, $limit);

        $query = $qb->getQuery();

        return $query->getResult();
    }

    public function getPublishedPageQueryBuilder($host = '', array $where = [], array $orderBy = [], int $limit = 0): QueryBuilder
    {
        $qb = $this->buildPublishedPageQuery('p');

        $this->andHost($qb, $host);
        $this->andWhere($qb, $where);
        $this->orderBy($qb, $orderBy);
        if ($limit) {
            $this->limit($qb, $limit);
        }

        return $qb;
    }

    private function buildPublishedPageQuery(string $alias = 'p'): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($alias)
            ->andWhere($alias.'.publishedAt <=  :nwo')
            ->setParameter('nwo', new \DateTime())
            ->orderBy($alias.'.publishedAt,'.$alias.'.priority', 'DESC');

        //$this->andNotRedirection($queryBuilder);

        return $queryBuilder;
    }

    public function getPage(string $slug, string $host, bool $checkId = true): ?PageInterface
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.slug =  :slug')->setParameter('slug', $slug);

        if ((int) $slug > 0 && $checkId) {
            $qb->orWhere('p.id =  :id')->setParameter('id', $slug);
        }

        $qb = $this->andHost($qb, $host);

        return $qb->getQuery()->getResult()[0] ?? null;
    }

    public function findByHost(string $host): array
    {
        $qb = $this->createQueryBuilder('p');
        $this->andHost($qb, $host);

        return $qb->getQuery()->getResult();
    }

    /**
     * Return page for sitemap and main Feed (PageController)
     * $qb->getQuery()->getResult();.
     */
    public function getIndexablePagesQuery(
        string $host,
        string $locale,
        ?int $limit = null
    ): QueryBuilder {
        $qb = $this->buildPublishedPageQuery('p');
        $qb = $this->andIndexable($qb);
        $qb = $this->andHost($qb, $host);
        $qb = $this->andLocale($qb, $locale);

        $this->andNotRedirection($qb);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb;
    }

    /**
     * Used in admin PageCrudController.
     */
    public function getPagesWithoutParent(): array
    {
        $q = $this->createQueryBuilder('p')
            ->andWhere('p.parentPage is NULL')
            ->orderBy('p.slug', 'DESC')
            ->getQuery();

        return $q->getResult();
    }

    /**
     * Used in admin Media.
     */
    public function getPagesUsingMedia(string $media): array
    {
        $qb = $this->createQueryBuilder('p');

        $or = $qb->expr()->orX();
        $or->add($qb->expr()->like('p.mainContent', ':apostrophMedia')); // catch: 'example.jpg'
        $or->add($qb->expr()->like('p.mainContent', ':quotedMedia')); // catch: "example.jpg'
        $or->add($qb->expr()->like('p.mainContent', ':defaultMedia')); // catch: media/default/example.jpg
        $or->add($qb->expr()->like('p.mainContent', ':thumbMedia'));
        $query = $qb->where($or)->setParameters([
            'apostrophMedia' => '%\''.$media.'\'%',
            'quotedMedia' => '%"'.$media.'"%',
            'defaultMedia' => '/media/default/'.$media.'%',
            'thumbMedia' => '/media/thumb/'.$media.'%',
        ])->getQuery();

        return $query->getResult();
    }

    private function getRootAlias(QueryBuilder $qb): string
    {
        $aliases = $qb->getRootAliases();

        if (! isset($aliases[0])) {
            throw new \RuntimeException('No alias was set before invoking getRootAlias().');
        }

        return $aliases[0];
    }

    /* ~~~~~~~~~~~~~~~ Query Builder Helper ~~~~~~~~~~~~~~~ */

    /**
     * QueryBuilder Helper.
     *
     * @param array $where array containing array with key,operator,value,key_prefix
     *                     Eg:
     *                     ['title', 'LIKE' '%this%'] => works
     *                     [['title', 'LIKE' '%this%']] => works
     *                     [['key'=>'title', 'operator' => 'LIKE', 'value' => '%this%'], ['key'=>'slug', 'operator' => 'LIKE', 'value' => '%this%']] => works
     */
    private function andWhere(QueryBuilder $qb, array $where): QueryBuilder
    {
        // Normalize array [']
        if (! empty($where) && (! isset($where[0]) || ! \is_array($where[0]))) {
            $where = [$where];
        }

        foreach ($where as $k => $w) {
            if (! \is_array($w)) {
                throw new Exception('malformated where params');
            }

            if (($w['value'] ?? $w[2]) === null) {
                $qb->andWhere(
                    ($w['key_prefix'] ?? $w[4] ?? 'p.').($w['key'] ?? $w[0]).
                    ' '.($w['operator'] ?? $w[1]).' NULL'
                );

                continue;
            }

            $qb->andWhere(
                ($w['key_prefix'] ?? $w[4] ?? 'p.').($w['key'] ?? $w[0])
                        .' '.($w['operator'] ?? $w[1])
                        .' :m'.$k
            )->setParameter('m'.$k, $w['value'] ?? $w[2]);
        }

        return $qb;
    }

    /**
     * @param array $orderBy containing key,direction
     */
    private function orderBy(QueryBuilder $qb, array $orderBy): QueryBuilder
    {
        if ([] === $orderBy) {
            return $qb;
        }

        $key = implode(',', array_map(
            function ($item) use ($qb) {
                return $this->getRootAlias($qb).'.'.$item;
            },
            explode(',', $orderBy['key'] ?? $orderBy[0])
        ));

        $qb->orderBy($key, $orderBy['direction'] ?? $orderBy[1] ?? 'DESC');

        return $qb;
    }

    /**
     * QueryBuilder Helper.
     *
     * @param string|array $host
     */
    public function andHost(QueryBuilder $qb, $host): QueryBuilder
    {
        if (! $host) {
            return $qb;
        }

        if (\is_string($host)) {
            $host = [$host];
        }

        return $qb->andWhere($this->getRootAlias($qb).'.host IN (:host)')
            ->setParameter('host', $host);
    }

    protected function andLocale(QueryBuilder $qb, string $locale): QueryBuilder
    {
        if (! $locale) {
            return $qb;
        }

        $alias = $this->getRootAlias($qb);

        return $qb->andWhere($alias.'.locale LIKE :locale')
                ->setParameter('locale', $locale);
    }

    protected function andIndexable(QueryBuilder $qb): QueryBuilder
    {
        $alias = $this->getRootAlias($qb);

        return $qb->andWhere($alias.'.metaRobots IS NULL OR '.$alias.'.metaRobots NOT LIKE :noi2')
            ->setParameter('noi2', '%noindex%');
    }

    protected function andNotRedirection(QueryBuilder $qb): QueryBuilder
    {
        $alias = $this->getRootAlias($qb);

        return $qb->andWhere($alias.'.mainContent NOT LIKE :noi')
            ->setParameter('noi', 'Location:%');
    }

    /**
     * Query Builder helper.
     *
     * @param int|array $limit containing start,max or just max
     */
    protected function limit($qb, $limit): QueryBuilder
    {
        if (! $limit) {
            return $qb;
        }

        if (\is_array($limit)) {
            return $qb->setFirstResult($limit['start'] ?? $limit[0])->setMaxResults($limit['max'] ?? $limit[1]);
        }

        return $qb->setMaxResults($limit + 1);
    }
}
