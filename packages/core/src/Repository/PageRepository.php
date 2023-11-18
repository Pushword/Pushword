<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Admin\PageCheatSheetAdmin;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageInterface;

/**
 * @psalm-suppress MethodSignatureMustProvideReturnType
 *
 * @extends ServiceEntityRepository<PageInterface>
 *
 * @method PageInterface|null  find($id, $lockMode = null, $lockVersion = null)
 * @method PageInterface|null  findOneBy(array $criteria, array $orderBy = null)
 * @method list<PageInterface> findAll()
 * @method list<PageInterface> findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @implements Selectable<int, PageInterface>
 * @implements ObjectRepository<PageInterface>
 */
#[\Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag('doctrine.repository_service')]
class PageRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
    protected bool $hostCanBeNull = false;

    /**
     * Can be used via a twig function.
     *
     * @param string|array<string>         $host
     * @param array<(string|int), string>  $orderBy
     * @param array<mixed>                 $where
     * @param int|array<(string|int), int> $limit
     *
     * @return PageInterface[]
     */
    public function getPublishedPages(
        string|array $host = '',
        array $where = [],
        array $orderBy = [],
        int|array $limit = 0,
        bool $withRedirection = true
    ) {
        $queryBuilder = $this->getPublishedPageQueryBuilder($host, $where, $orderBy);

        if (! $withRedirection) {
            $this->andNotRedirection($queryBuilder);
        }

        $this->limit($queryBuilder, $limit);

        $query = $queryBuilder->getQuery();

        return $query->getResult(); // @phpstan-ignore-line
    }

    /**
     * Can be used via a twig function.
     *
     * @param string|array<string>         $host
     * @param array<(string|int), string>  $orderBy
     * @param array<mixed>                 $where
     * @param int|array<(string|int), int> $limit
     */
    public function getPublishedPageQueryBuilder(string|array $host = '', array $where = [], array $orderBy = [], int|array $limit = 0): QueryBuilder
    {
        $qb = $this->buildPublishedPageQuery('p');

        $this->andHost($qb, $host);
        $this->andWhere($qb, $where);
        $this->orderBy($qb, $orderBy);
        $this->limit($qb, $limit);

        return $qb;
    }

    private function buildPublishedPageQuery(string $alias = 'p'): QueryBuilder
    {
        // $this->andNotRedirection($queryBuilder);

        return $this->createQueryBuilder($alias)
            ->andWhere($alias.'.publishedAt <=  :now')
            ->setParameter('now', new \DateTime(), 'datetime')
            ->andWhere($alias.'.slug <> :cheatsheet')
            ->setParameter('cheatsheet', PageCheatSheetAdmin::CHEATSHEET_SLUG);
    }

    /**
     * @param string|string[] $host
     */
    public function getPage(string $slug, string|array $host, bool $checkId = true): ?PageInterface
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.slug =  :slug')->setParameter('slug', $slug);

        if ((int) $slug > 0 && $checkId) {
            $qb->orWhere('p.id =  :id')->setParameter('id', $slug);
        }

        $qb = $this->andHost($qb, $host);

        return $qb->getQuery()->getResult()[0] ?? null; // @phpstan-ignore-line
    }

    /**
     * @param string|string[] $host
     *
     * @return PageInterface[]
     */
    public function findByHost(string|array $host): array
    {
        $qb = $this->createQueryBuilder('p');
        $this->andHost($qb, $host);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param string|string[] $host
     *                              Return page for sitemap and main Feed (PageController)
     *                              $qb->getQuery()->getResult();
     */
    public function getIndexablePagesQuery(
        string|array $host,
        string $locale,
        int $limit = null
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
     *
     * @return PageInterface[]
     */
    public function getPagesWithoutParent(): array
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.parentPage is NULL')
            ->orderBy('p.slug', Criteria::DESC)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Used in admin Media.
     *
     * @return PageInterface[]
     */
    public function getPagesUsingMedia(MediaInterface $media): array
    {
        $qb = $this->createQueryBuilder('p');

        $orx = $qb->expr()->orX();
        $orx->add($qb->expr()->eq('p.mainImage', ':idMedia'));
        $orx->add($qb->expr()->like('p.mainContent', ':nameMedia')); // catch: 'example'
        $orx->add($qb->expr()->like('p.mainContent', ':apostrophMedia')); // catch: 'example.jpg'
        $orx->add($qb->expr()->like('p.mainContent', ':quotedMedia')); // catch: "example.jpg'
        $orx->add($qb->expr()->like('p.mainContent', ':defaultMedia')); // catch: media/default/example.jpg
        $orx->add($qb->expr()->like('p.mainContent', ':thumbMedia'));

        $query = $qb->where($orx)->setParameters([ // @phpstan-ignore-line
            'idMedia' => $media->getId(),
            'nameMedia' => "%'".$media->getName()."'%",
            'apostrophMedia' => "%'".$media->getMedia()."'%",
            'quotedMedia' => '%"'.$media->getMedia().'"%',
            'defaultMedia' => '/media/default/'.$media->getMedia().'%',
            'thumbMedia' => '/media/thumb/'.$media->getMedia().'%',
        ])->getQuery();

        return $query->getResult(); // @phpstan-ignore-line
    }

    private function getRootAlias(QueryBuilder $queryBuilder): string
    {
        $aliases = $queryBuilder->getRootAliases();

        if (! isset($aliases[0])) {
            throw new \RuntimeException('No alias was set before invoking getRootAlias().');
        }

        return $aliases[0];
    }

    /* ~~~~~~~~~~~~~~~ Query Builder Helper ~~~~~~~~~~~~~~~ */

    /**
     * QueryBuilder Helper.
     *
     * @param array<mixed> $where array containing array with key,operator,value,key_prefix
     *                            Eg:
     *                            ['title', 'LIKE' '%this%'] => works
     *                            [['title', 'LIKE' '%this%']] => works
     *                            [['key'=>'title', 'operator' => 'LIKE', 'value' => '%this%'], 'OR', ['key'=>'slug', 'operator' => 'LIKE', 'value' => '%this%']] => works
     *                            See confusing parenthesis DQL doctrine https://symfonycasts.com/screencast/doctrine-queries/and-where-or-where#avoid-orwhere-and-where
     */
    private function andWhere(QueryBuilder $queryBuilder, array $where): QueryBuilder
    {
        // Normalize array [']
        if ([] !== $where && (! isset($where[0]) || ! \is_array($where[0]))) {
            $where = [$where];
        }

        if (\in_array('OR', $where, true)) {
            return $this->andWhereOr($queryBuilder, $where);
        }

        foreach ($where as $singleWhere) {
            if (! \is_array($singleWhere)) {
                throw new \Exception('malformated where params');
            }

            $this->simpleAndWhere($queryBuilder, $singleWhere);
        }

        return $queryBuilder;
    }

    /**
     * @param array<mixed> $where
     */
    private function andWhereOr(QueryBuilder $queryBuilder, array $where): QueryBuilder
    {
        $orX = $queryBuilder->expr()->orX();

        foreach ($where as $singleWhere) {
            if (! \is_array($singleWhere)) {
                continue;
            }

            $k = md5('a'.random_int(0, mt_getrandmax()));
            $orX->add($queryBuilder->expr()->like(($singleWhere['key_prefix'] ?? $singleWhere[4] ?? 'p.').($singleWhere['key'] ?? $singleWhere[0]), ':m'.$k));
            $queryBuilder->setParameter('m'.$k, $singleWhere['value'] ?? $singleWhere[2]);
        }

        return $queryBuilder->andWhere($orX);
    }

    /**
     * @param array<mixed> $w
     */
    private function simpleAndWhere(QueryBuilder $queryBuilder, array $w): QueryBuilder
    {
        if (($w['value'] ?? $w[2]) === null) {
            return $queryBuilder->andWhere(
                ($w['key_prefix'] ?? $w[4] ?? 'p.').($w['key'] ?? $w[0]).
                    ' '.($w['operator'] ?? $w[1]).' NULL'
            );
        }

        $k = md5('a'.random_int(0, mt_getrandmax()));

        return $queryBuilder->andWhere(
            ($w['key_prefix'] ?? $w[4] ?? 'p.').($w['key'] ?? $w[0])
                        .' '.($w['operator'] ?? $w[1])
                        .(($w['operator'] ?? $w[1]) === 'IN' ? '( :m'.$k.')' : ' :m'.$k)
        )->setParameter('m'.$k, $w['value'] ?? $w[2]);
    }

    /**
     * @param array<(string|int), string> $orderBy containing key,direction
     */
    private function orderBy(QueryBuilder $queryBuilder, array $orderBy): QueryBuilder
    {
        if ([] === $orderBy) {
            return $queryBuilder;
        }

        $keys = explode(',', $orderBy['key'] ?? $orderBy[0]);
        foreach ($keys as $i => $key) {
            $direction = $this->extractDirection($key, $orderBy);
            $orderByFunc = 0 === $i ? 'orderBy' : 'addOrderBy';
            if (! method_exists($queryBuilder, $orderByFunc)) {
                throw new \LogicException();
            }

            $queryBuilder->$orderByFunc($this->getRootAlias($queryBuilder).'.'.$key, $direction); // @phpstan-ignore-line
        }

        return $queryBuilder;
    }

    /**
     * @param array<(string|int), string> $orderBy
     */
    private function extractDirection(string &$key, array $orderBy): string
    {
        if (! str_contains($key, ' ')) {
            return $orderBy['direction'] ?? $orderBy[1] ?? 'DESC';
        }

        $keyDir = explode(' ', $key, 2);
        $key = $keyDir[0];

        return $keyDir[1];
    }

    /**
     * QueryBuilder Helper.
     *
     * @param string|string[] $host
     */
    public function andHost(QueryBuilder $queryBuilder, string|array $host): QueryBuilder
    {
        if (\in_array($host, ['', []], true)) {
            return $queryBuilder;
        }

        if (\is_string($host)) {
            $host = [$host];
        }

        return $queryBuilder->andWhere($this->getRootAlias($queryBuilder).'.host IN (:host)')
            ->setParameter('host', $host);
    }

    protected function andLocale(QueryBuilder $queryBuilder, string $locale): QueryBuilder
    {
        if ('' === $locale) {
            return $queryBuilder;
        }

        if ('0' === $locale) {
            return $queryBuilder;
        }

        $alias = $this->getRootAlias($queryBuilder);

        return $queryBuilder->andWhere($alias.'.locale LIKE :locale')
                ->setParameter('locale', $locale);
    }

    protected function andIndexable(QueryBuilder $queryBuilder): QueryBuilder
    {
        $alias = $this->getRootAlias($queryBuilder);

        return $queryBuilder->andWhere($alias.'.metaRobots IS NULL OR '.$alias.'.metaRobots NOT LIKE :noi2')
            ->setParameter('noi2', '%noindex%');
    }

    protected function andNotRedirection(QueryBuilder $queryBuilder): QueryBuilder
    {
        $alias = $this->getRootAlias($queryBuilder);

        return $queryBuilder->andWhere($alias.'.mainContent NOT LIKE :noi')
            ->setParameter('noi', 'Location:%');
    }

    /**
     * Query Builder helper.
     *
     * @param int|array<(string|int), int> $limit containing start,max or just max
     */
    protected function limit(QueryBuilder $qb, array|int $limit): QueryBuilder
    {
        if (\in_array($limit, [0, []], true)) {
            return $qb;
        }

        if (\is_array($limit)) {
            return $qb->setFirstResult($limit['start'] ?? $limit[0])->setMaxResults($limit['max'] ?? $limit[1]);
        }

        return $qb->setMaxResults($limit);
    }
}
