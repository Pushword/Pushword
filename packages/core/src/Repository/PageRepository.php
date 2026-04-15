<?php

namespace Pushword\Core\Repository;

use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Events;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use LogicException;
use Pushword\Admin\Controller\PageCheatSheetCrudController;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\ValueObject\PageRedirection;
use RuntimeException;

/**
 * @extends ServiceEntityRepository<Page>
 *
 * @implements Selectable<int, Page>
 * @implements ObjectRepository<Page>
 *
 * @method Page[] findAll()
 */
#[AsDoctrineListener(event: Events::onClear)]
class PageRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
    use TagsRepositoryTrait;

    /** @var array<string, array<string, Page>> host => [slug => Page] */
    private array $slugCache = [];

    /** @var array<string, true> */
    private array $warmedHosts = [];

    /**
     * Slug-existence set per host. Used by the page() Twig function to decide
     * whether an internal redirect target is a known page (in which case the
     * router generates a URL) or an external URL (returned as-is).
     *
     * @var array<string, array<string, true>>
     */
    private array $slugSets = [];

    /**
     * Redirect map per host. Only rows whose main_content starts with
     * "Location:" are kept — on a typical site this is a handful of entries
     * regardless of total page count.
     *
     * @var array<string, array<string, array{url: string, code: int}>>
     */
    private array $redirectMaps = [];

    /** @var array<string, true> */
    private array $warmedLightHosts = [];

    public function __construct(
        ManagerRegistry $registry,
        // #[Autowire('%pw.public_media_dir%')]
        // private readonly string $publicMediaDir,
    ) {
        parent::__construct($registry, Page::class);
    }

    /**
     * Preload all pages for a host into the slug cache.
     * Call this before batch operations (static generation, page scanning) to avoid N+1 queries.
     */
    public function warmupSlugCache(string $host): void
    {
        if (isset($this->warmedHosts[$host])) {
            return;
        }

        $pages = $this->findByHost($host);

        $this->slugCache[$host] = [];
        foreach ($pages as $page) {
            $this->slugCache[$host][$page->getSlug()] = $page;
        }

        $this->warmedHosts[$host] = true;
    }

    /**
     * Get a page by slug with per-slug caching. Does NOT auto-warm the full-entity
     * cache: callers that need to preload an entire host should call warmupSlugCache()
     * explicitly (static generation, page scanning). The hot render path goes through
     * resolvePageUriTarget() instead, which uses a scalar light cache.
     */
    public function getPageBySlug(string $slug, string $host): ?Page
    {
        if (isset($this->warmedHosts[$host])) {
            return $this->slugCache[$host][$slug] ?? null;
        }

        if (isset($this->slugCache[$host][$slug])) {
            return $this->slugCache[$host][$slug];
        }

        $page = $this->findOneBy(['slug' => $slug, 'host' => $host]);

        if (null !== $page) {
            $this->slugCache[$host][$slug] = $page;
        }

        return $page;
    }

    /**
     * Populate the lightweight URI cache for a host with a single scalar query.
     * Builds two structures: a slug-existence set (used to detect internal
     * redirect targets) and a redirect map keyed by slug. The redirect map is
     * typically a handful of entries even on large sites.
     */
    public function warmupSlugCacheLight(string $host): void
    {
        if (isset($this->warmedLightHosts[$host])) {
            return;
        }

        $meta = $this->getClassMetadata();
        $table = $meta->getTableName();
        $slugCol = $meta->getColumnName('slug');
        $hostCol = $meta->getColumnName('host');
        $contentCol = $meta->getColumnName('mainContent');

        $sql = sprintf('SELECT %s AS slug,', $slugCol)
            .sprintf(" CASE WHEN %s LIKE 'Location:%%' THEN %s ELSE NULL END AS redirect_content", $contentCol, $contentCol)
            .sprintf(' FROM %s WHERE %s = ?', $table, $hostCol);

        /** @var list<array{slug: string, redirect_content: ?string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [$host]);

        $slugSet = [];
        $redirects = [];
        foreach ($rows as $row) {
            $slug = $row['slug'];
            $slugSet[$slug] = true;

            $redirectContent = $row['redirect_content'];
            if (null === $redirectContent) {
                continue;
            }

            $redirection = PageRedirection::fromContent($redirectContent);
            if (null === $redirection) {
                continue;
            }

            $redirects[$slug] = ['url' => $redirection->url, 'code' => $redirection->code];
        }

        $this->slugSets[$host] = $slugSet;
        $this->redirectMaps[$host] = $redirects;
        $this->warmedLightHosts[$host] = true;
    }

    /**
     * Whether a slug exists for the given host (by scalar light cache).
     * Used by the page() Twig function to decide whether an internal redirect
     * target can be handed to the router. First call per host triggers the
     * warmup; subsequent calls are plain array reads. Returns false for the
     * empty-host sentinel (caller resolves via SiteRegistry::getMainHost()).
     */
    public function hasSlug(string $slug, string $host): bool
    {
        if ('' === $host) {
            return false;
        }

        if (! isset($this->warmedLightHosts[$host])) {
            $this->warmupSlugCacheLight($host);
        }

        return isset($this->slugSets[$host][$slug]);
    }

    /**
     * Return the redirect target for a slug, or null if the slug is unknown
     * or not a redirect. Triggers warmup on first call per host.
     *
     * @return ?array{url: string, code: int}
     */
    public function getRedirectFor(string $slug, string $host): ?array
    {
        if ('' === $host) {
            return null;
        }

        if (! isset($this->warmedLightHosts[$host])) {
            $this->warmupSlugCacheLight($host);
        }

        return $this->redirectMaps[$host][$slug] ?? null;
    }

    /**
     * Check if a host's full Page entities are loaded in cache.
     */
    public function isHostWarmed(string $host): bool
    {
        return isset($this->warmedHosts[$host]);
    }

    /**
     * Check if a host's lightweight URI cache is populated.
     */
    public function isHostLightWarmed(string $host): bool
    {
        return isset($this->warmedLightHosts[$host]);
    }

    /**
     * Clear all internal slug caches.
     * Called automatically when EntityManager::clear() is invoked.
     */
    public function onClear(): void
    {
        $this->slugCache = [];
        $this->warmedHosts = [];
        $this->slugSets = [];
        $this->redirectMaps = [];
        $this->warmedLightHosts = [];
    }

    public function create(string $host): Page
    {
        $page = new Page();
        $page->host = $host;

        return $page;
    }

    protected bool $hostCanBeNull = false;

    /**
     * Can be used via a twig function.
     *
     * @param string|array<string>         $host
     * @param array<(string|int), string>  $orderBy
     * @param array<mixed>                 $where
     * @param int|array<(string|int), int> $limit
     *
     * @return Page[]
     */
    public function getPublishedPages(
        string|array $host = '',
        array $where = [],
        array $orderBy = [],
        int|array $limit = 0,
        bool $withRedirection = true
    ): mixed {
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
        $queryBuilder = $this->buildPublishedPageQuery('p');

        $this->andHost($queryBuilder, $host);
        new FilterWhereParser($queryBuilder, $where)->parseAndAdd();
        $this->orderBy($queryBuilder, $orderBy);
        $this->limit($queryBuilder, $limit);

        return $queryBuilder;
    }

    private function buildPublishedPageQuery(string $alias = 'p'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias.'.parentPage', 'parent')->addSelect('parent')
            ->leftJoin($alias.'.mainImage', 'mainImage')->addSelect('mainImage')
            ->andWhere($alias.'.publishedAt IS NOT NULL')
            ->andWhere($alias.'.publishedAt <=  :now')
            ->setParameter('now', new DateTime(), 'datetime')
            ->andWhere($alias.'.slug <> :cheatsheet')
            ->setParameter('cheatsheet', PageCheatSheetCrudController::CHEATSHEET_SLUG);
    }

    /** @return Page[] */
    public function findNewlyPublishedSince(DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.publishedAt IS NOT NULL')
            ->andWhere('p.publishedAt > :since')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('since', $since, 'datetime')
            ->setParameter('now', new DateTime(), 'datetime')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string|string[] $host
     */
    public function getPage(string $slug, string|array $host): ?Page
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->andWhere('p.slug =  :slug')->setParameter('slug', $slug);

        $queryBuilder = $this->andHost($queryBuilder, $host);

        return $queryBuilder->getQuery()->getResult()[0] ?? null;  // @phpstan-ignore-line
    }

    /**
     * @param string|string[] $host
     *
     * @return Page[]
     */
    public function findByHost(string|array $host): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->leftJoin('p.parentPage', 'parent')->addSelect('parent')
            ->leftJoin('p.mainImage', 'mainImage')->addSelect('mainImage');
        $this->andHost($queryBuilder, $host);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param string|string[] $host
     *                              Return page for sitemap and main Feed (PageController)
     *                              $queryBuilder->getQuery()->getResult();
     */
    public function getIndexablePagesQuery(
        string|array $host,
        string $locale,
        ?int $limit = null
    ): QueryBuilder {
        $queryBuilder = $this->buildPublishedPageQuery('p');
        $queryBuilder = $this->andIndexable($queryBuilder);
        $queryBuilder = $this->andHost($queryBuilder, $host);
        $queryBuilder = $this->andLocale($queryBuilder, $locale);

        $this->andNotRedirection($queryBuilder);

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder;
    }

    /**
     * Used in admin PageCrudController.
     *
     * @return Page[]
     */
    public function getPagesWithoutParent(): array
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.parentPage is NULL')
            ->orderBy('p.slug', 'DESC')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Used in admin Media.
     * Finds pages that reference a given media file.
     *
     * @return Page[]
     */
    public function getPagesUsingMedia(Media $media, ?string $host = null): array
    {
        $queryBuilder = $this->createQueryBuilder('p');

        if (null !== $host) {
            $queryBuilder->andWhere('p.host = :host')->setParameter('host', $host);
        }

        $orx = $queryBuilder->expr()->orX();

        // Direct relation via mainImage
        $orx->add($queryBuilder->expr()->eq('p.mainImage', ':idMedia'));

        // Escape special characters for LIKE patterns
        $escapedFileName = $this->escapeLikePattern($media->getFileName());
        $escapedAlt = $this->escapeLikePattern($media->getAlt());

        // Search for filename in content (with proper escaping)
        $orx->add($queryBuilder->expr()->like('p.mainContent', ':fileNamePattern'));

        $query = $queryBuilder->where($orx)
            ->setParameter('idMedia', $media->id)
            ->setParameter('fileNamePattern', '%'.$escapedFileName.'%');

        return $query->getQuery()->getResult();
    }

    /**
     * Escape special characters for LIKE pattern matching.
     */
    private function escapeLikePattern(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private function getRootAlias(QueryBuilder $queryBuilder): string
    {
        $aliases = $queryBuilder->getRootAliases();

        if (! isset($aliases[0])) {
            throw new RuntimeException('No alias was set before invoking getRootAlias().');
        }

        return $aliases[0];
    }

    /* ~~~~~~~~~~~~~~~ Query Builder Helper ~~~~~~~~~~~~~~~ */

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
                throw new LogicException();
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

    public function andLocale(QueryBuilder $queryBuilder, string $locale): QueryBuilder
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

    public function andIndexable(QueryBuilder $queryBuilder): QueryBuilder
    {
        $alias = $this->getRootAlias($queryBuilder);

        return $queryBuilder->andWhere($alias.'.metaRobots IS NULL OR '.$alias.'.metaRobots NOT LIKE :noi2')
            ->setParameter('noi2', '%noindex%');
    }

    public function andNotRedirection(QueryBuilder $queryBuilder): QueryBuilder
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
    protected function limit(QueryBuilder $queryBuilder, array|int $limit): QueryBuilder
    {
        if (\in_array($limit, [0, []], true)) {
            return $queryBuilder;
        }

        if (\is_array($limit)) {
            return $queryBuilder->setFirstResult($limit['start'] ?? $limit[0])->setMaxResults($limit['max'] ?? $limit[1]);
        }

        return $queryBuilder->setMaxResults($limit);
    }

    /**
     * @param string|string[]|null $host
     *
     * @return string[]
     */
    public function getAllTags(array|string|null $host = null): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p.tags')
            ->setMaxResults(30000);

        if (null !== $host) {
            $this->andHost($queryBuilder, $host);
        }

        /** @var array{tags: string[]}[] */
        $tags = $queryBuilder->getQuery()->getResult();

        return $this->flattenTags($tags);
    }

    /**
     * @param string|string[]|null $host
     *
     * @return string[]
     */
    public function getPageUriList(array|string|null $host = null): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p.slug')
            ->setMaxResults(30000); // some kind of arbitrary parapet

        if (null !== $host) {
            $this->andHost($queryBuilder, $host);
        }

        /** @var array{slug: string}[] */
        $results = $queryBuilder->getQuery()->getResult();

        return array_map(
            static fn (array $r): string => '/'.('homepage' === $r['slug'] ? '' : $r['slug']),
            $results,
        );
    }
}
