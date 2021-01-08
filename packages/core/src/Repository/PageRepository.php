<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Pushword\Core\Entity\PageInterface as Page;

/**
 * @method Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Page|null findOneBy(array $criteria, array $orderBy = null)
 * @method list<T>   findAll()
 * @method list<T>   findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends ServiceEntityRepository implements PageRepositoryInterface
{
    protected $hostCanBeNull = false;

    /**
     * This one is useful, but totaly not instinctive.
     */
    public function getPublishedPages($host = '', array $where = [], array $orderBy = [], int $limit = 0)
    {
        $qb = $this->getQueryToFindPublished('p');

        if ($host) {
            if (\is_array($host)) {
                $this->andHost($qb, $host[0], $host[1]);
            } else {
                $this->andHost($qb, $host, $this->hostCanBeNull);
            }
        }

        if (! empty($where) && (! isset($where[0]) || ! \is_array($where[0]))) {
            $where = [$where];
        }

        foreach ($where as $k => $w) {
            $qb->andWhere('p.'.($w['key'] ?? $w[0]).' '.($w['operator'] ?? $w[1]).' :m'.$k)
                ->setParameter('m'.$k, $w['value'] ?? $w[2]);
        }

        if ($orderBy) {
            $qb->orderBy('p.'.($orderBy['key'] ?? $orderBy[0]), $orderBy['direction'] ?? $orderBy[1]);
        }

        if ($limit) {
            $qb->setMaxResults($limit + 1);
        }

        return $qb->getQuery()->getResult();
    }

    protected function getQueryToFindPublished($p): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($p)
            ->andWhere($p.'.createdAt <=  :nwo')
            ->setParameter('nwo', new \DateTime())
            ->orderBy($p.'.createdAt', 'DESC');

        $this->andNotRedirection($queryBuilder);

        return $queryBuilder;
    }

    public function getPage($slug, $host, $hostCanBeNull): ?Page
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.slug =  :slug')->setParameter('slug', $slug);

        if ((int) $slug > 0) {
            $qb->orWhere('p.id =  :slug')->setParameter('slug', $slug);
        }

        $qb = $this->andHost($qb, $host, $hostCanBeNull);

        return $qb->getQuery()->getResult()[0] ?? null;
    }

    protected function andNotRedirection(QueryBuilder $qb): QueryBuilder
    {
        return $qb->andWhere('p.mainContent IS NULL OR p.mainContent NOT LIKE :noi')
            ->setParameter('noi', 'Location:%');
    }

    protected function andIndexable(QueryBuilder $qb): QueryBuilder
    {
        return $qb->andWhere('p.metaRobots IS NULL OR p.metaRobots NOT LIKE :noi2')
            ->setParameter('noi2', '%noindex%');
    }

    public function findByHost($host, $hostCanBeNull = false): array
    {
        $qb = $this->createQueryBuilder('p');
        $this->andHost($qb, $host, $hostCanBeNull);

        return $qb->getQuery()->getResult();
    }

    public function andHost(QueryBuilder $qb, $host, $hostCanBeNull = false): QueryBuilder
    {
        if (\is_string($host)) {
            $host = [$host];
        }

        if (! $hostCanBeNull && array_search(null, $host)) {
            $hostCanBeNull = true;
        }

        $qb->andWhere('p.host IN (:host)'.($hostCanBeNull ? ' OR p.host IS NULL' : ''))
            ->setParameter('host', $host);

        return $qb;
    }

    protected function andLocale(QueryBuilder $qb, $locale, $defaultLocale): QueryBuilder
    {
        return $qb->andWhere(($defaultLocale == $locale ? 'p.locale IS NULL OR ' : '').'p.locale LIKE :locale')
                ->setParameter('locale', $locale);
    }

    /**
     * Return page for sitemap
     * $qb->getQuery()->getResult();.
     */
    public function getIndexablePages(
        $host,
        $hostCanBeNull,
        $locale,
        $defaultLocale,
        ?int $limit = null
    ): QueryBuilder {
        $qb = $this->getQueryToFindPublished('p');
        $qb = $this->andIndexable($qb);
        $qb = $this->andNotRedirection($qb);
        $qb = $this->andHost($qb, $host, $hostCanBeNull);
        $qb = $this->andLocale($qb, $locale, $defaultLocale);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb;
    }

    public function getPagesWithoutParent()
    {
        $q = $this->createQueryBuilder('p')
            ->andWhere('p.parentPage is NULL')
            ->orderBy('p.slug', 'DESC')
            ->getQuery();

        return $q->getResult();
    }

    public function getPagesUsingMedia($media)
    {
        $q = $this->createQueryBuilder('p')
            ->andWhere('p.mainContent LIKE :val')
            ->setParameter('val', '%'.$media.'%')
            ->getQuery()
        ;

        return $q->getResult();
    }

    /**
     * Set the value of hostCanBeNull.
     *
     * @return self
     */
    public function setHostCanBeNull($hostCanBeNull)
    {
        $this->hostCanBeNull = $hostCanBeNull;

        return $this;
    }
}
