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
 *
 * @phpstan-type CorpusState array{pages: int, lastEditAt: int|null}
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
     * Slugs that have been definitively resolved (found or not) per host, so
     * getPageBySlug() can short-circuit to the slug cache (or null) without a
     * query. Populated by single lookups and by warmupSlugCacheFor().
     *
     * @var array<string, array<string, true>>
     */
    private array $resolvedSlugs = [];

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

    /**
     * Reverse redirect map per host, built from each page's `redirectFrom` column:
     * old path => destination slug + code. Old paths that collide with a real (or
     * phantom) page slug are skipped — that page wins (see coexistence rule).
     *
     * @var array<string, array<string, array{slug: string, code: int}>>
     */
    private array $redirectFromMaps = [];

    /** @var array<string, true> */
    private array $warmedLightHosts = [];

    /** @var array<string, bool> host => whether any variant page exists */
    private array $hasVariantCache = [];

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
        if (isset($this->warmedHosts[$host]) || isset($this->resolvedSlugs[$host][$slug])) {
            return $this->slugCache[$host][$slug] ?? null;
        }

        $page = $this->findOneBy(['slug' => $slug, 'host' => $host]);

        if (null !== $page) {
            $this->slugCache[$host][$slug] = $page;
        }

        $this->resolvedSlugs[$host][$slug] = true;

        return $page;
    }

    /**
     * Batch-resolve a set of slugs for a host in a single query, populating the
     * per-slug cache. The render pipeline (LinkCollector) calls this with the
     * internal-link slugs found in a page body so the downstream Html*Link
     * filters resolve them from cache instead of each firing one getPageBySlug()
     * per link. Both hits and misses are marked resolved, so a slug warmed here
     * never triggers a follow-up query.
     *
     * @param string[] $slugs
     */
    public function warmupSlugCacheFor(array $slugs, string $host): void
    {
        if (isset($this->warmedHosts[$host])) {
            return;
        }

        $pending = [];
        foreach ($slugs as $slug) {
            if ('' !== $slug && ! isset($this->resolvedSlugs[$host][$slug])) {
                $pending[$slug] = true;
            }
        }

        if ([] === $pending) {
            return;
        }

        $pendingSlugs = array_keys($pending);

        foreach ($this->findBy(['slug' => $pendingSlugs, 'host' => $host]) as $page) {
            $this->slugCache[$host][$page->getSlug()] = $page;
        }

        foreach ($pendingSlugs as $slug) {
            $this->resolvedSlugs[$host][$slug] = true;
        }
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
        $redirectFromCol = $meta->getColumnName('redirectFrom');

        $sql = sprintf('SELECT %s AS slug, %s AS redirect_from,', $slugCol, $redirectFromCol)
            .sprintf(" CASE WHEN %s LIKE 'Location:%%' THEN %s ELSE NULL END AS redirect_content", $contentCol, $contentCol)
            .sprintf(' FROM %s WHERE %s = ?', $table, $hostCol);

        /** @var list<array{slug: string, redirect_from: ?string, redirect_content: ?string}> $rows */
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

        // Second pass: a redirectFrom path only resolves when no real/phantom page owns it.
        $redirectFrom = [];
        foreach ($rows as $row) {
            $redirectFromJson = $row['redirect_from'];
            if (null === $redirectFromJson) {
                continue;
            }

            if ('' === $redirectFromJson) {
                continue;
            }

            /** @var array<string, mixed> $map */
            $map = json_decode($redirectFromJson, true) ?: [];
            foreach ($map as $from => $code) {
                if (isset($slugSet[$from])) {
                    continue;
                }

                if (isset($redirectFrom[$from])) {
                    continue;
                }

                $redirectFrom[$from] = ['slug' => $row['slug'], 'code' => is_numeric($code) ? (int) $code : 301];
            }
        }

        $this->slugSets[$host] = $slugSet;
        $this->redirectMaps[$host] = $redirects;
        $this->redirectFromMaps[$host] = $redirectFrom;
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

        return isset($this->slugSets[$host][$slug]) || isset($this->redirectFromMaps[$host][$slug]);
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

        if (isset($this->redirectMaps[$host][$slug])) {
            return $this->redirectMaps[$host][$slug];
        }

        $redirectFrom = $this->redirectFromMaps[$host][$slug] ?? null;

        return null !== $redirectFrom
            ? ['url' => '/'.$redirectFrom['slug'], 'code' => $redirectFrom['code']]
            : null;
    }

    /**
     * Resolve an old path registered in some page's redirectFrom to that page's
     * current slug, or null when the path is not a known redirectFrom entry.
     * Lets the render pipeline rewrite an internal link pointing at a renamed
     * page's former slug to its current slug, so the link targets the page
     * directly instead of relying on a 301 hop (mirrors how a renamed media is
     * resolved by its fileNameHistory). Old paths that collide with a live page
     * slug are absent from the map (that page wins — see warmupSlugCacheLight),
     * so this never shadows a real page.
     */
    public function resolveRedirectFromSlug(string $slug, string $host): ?string
    {
        if ('' === $host) {
            return null;
        }

        if (! isset($this->warmedLightHosts[$host])) {
            $this->warmupSlugCacheLight($host);
        }

        return $this->redirectFromMaps[$host][$slug]['slug'] ?? null;
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
        $this->resolvedSlugs = [];
        $this->slugSets = [];
        $this->redirectMaps = [];
        $this->redirectFromMaps = [];
        $this->warmedLightHosts = [];
        $this->hasVariantCache = [];
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
     * A cheap fingerprint of what {@see getPublishedPages()} would return for a host,
     * for callers that cache a whole-corpus derivative (the link graph) and need to
     * know whether it still describes the site.
     *
     * Count *and* last edit, because neither alone is enough: an edit moves the
     * timestamp without moving the count, while a deletion — or a page whose
     * scheduled publishedAt has since passed — moves the count without touching any
     * timestamp. Cheap enough to call on every read: two aggregates, no hydration.
     *
     * Compare states for equality, never for order. A timestamp is only a proxy for
     * "the corpus changed", and one that runs backwards (a restored page, a flat
     * sync writing its own updatedAt) still means exactly that.
     *
     * @return array{pages: int, lastEditAt: int|null} lastEditAt is null on an empty corpus
     */
    public function getPublishedCorpusState(string $host): array
    {
        $queryBuilder = $this->buildPublishedPageQuery('p');
        $this->andHost($queryBuilder, $host);

        /** @var array{pages: int|string, lastEditAt: string|null} $state */
        $state = $queryBuilder
            ->select('COUNT(p.id) AS pages', 'MAX(p.updatedAt) AS lastEditAt')
            ->getQuery()
            ->getSingleResult();

        return [
            'pages' => (int) $state['pages'],
            'lastEditAt' => null === $state['lastEditAt'] ? null : (int) new DateTime($state['lastEditAt'])->format('U'),
        ];
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
        return $this->applyListCriteria($this->buildPublishedPageQuery('p'), $host, $where, $orderBy, $limit);
    }

    /**
     * Same as getPublishedPageQueryBuilder() over the complementary set: the pages
     * not online right now. Backs the draft_list() twig function.
     *
     * @param string|array<string>         $host
     * @param array<(string|int), string>  $orderBy
     * @param array<mixed>                 $where
     * @param int|array<(string|int), int> $limit
     */
    public function getUnpublishedPageQueryBuilder(string|array $host = '', array $where = [], array $orderBy = [], int|array $limit = 0): QueryBuilder
    {
        return $this->applyListCriteria($this->buildUnpublishedPageQuery('p'), $host, $where, $orderBy, $limit);
    }

    /**
     * @param string|array<string>         $host
     * @param array<mixed>                 $where
     * @param array<(string|int), string>  $orderBy
     * @param int|array<(string|int), int> $limit
     */
    private function applyListCriteria(QueryBuilder $queryBuilder, string|array $host, array $where, array $orderBy, int|array $limit): QueryBuilder
    {
        $this->andHost($queryBuilder, $host);
        new FilterWhereParser($queryBuilder, $where)->parseAndAdd();
        $this->orderBy($queryBuilder, $orderBy);
        $this->limit($queryBuilder, $limit);

        return $queryBuilder;
    }

    /**
     * Joins and exclusions shared by the published and unpublished queries, so the
     * two stay strict complements of each other: only the publishedAt condition
     * differs between them.
     */
    private function buildPageQuery(string $alias = 'p'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias.'.parentPage', 'parent')->addSelect('parent')
            ->leftJoin($alias.'.mainImage', 'mainImage')->addSelect('mainImage')
            ->andWhere($alias.'.slug <> :cheatsheet')
            ->setParameter('cheatsheet', PageCheatSheetCrudController::CHEATSHEET_SLUG);
    }

    private function buildPublishedPageQuery(string $alias = 'p'): QueryBuilder
    {
        return $this->buildPageQuery($alias)
            ->andWhere($alias.'.publishedAt IS NOT NULL')
            ->andWhere($alias.'.publishedAt <=  :now')
            ->setParameter('now', new DateTime(), 'datetime');
    }

    /**
     * Pages not online right now: never scheduled (publishedAt IS NULL) or scheduled
     * for later. Strict complement of buildPublishedPageQuery().
     */
    private function buildUnpublishedPageQuery(string $alias = 'p'): QueryBuilder
    {
        $queryBuilder = $this->buildPageQuery($alias);

        return $queryBuilder
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull($alias.'.publishedAt'),
                $queryBuilder->expr()->gt($alias.'.publishedAt', ':now'),
            ))
            ->setParameter('now', new DateTime(), 'datetime');
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
     * Hydrate pages by id with parentPage and mainImage eager-joined, returned in
     * the order of the supplied ids (SQL IN does not preserve order; unknown ids are
     * dropped). Consumers that resolve page ids through a custom query — search
     * relevance, related pages, a hand-written id-IN — should rehydrate through this
     * instead of a bare id fetch, which re-introduces a per-row mainImage N+1 once the
     * result set is card- or list-rendered. Mirrors the joins buildPublishedPageQuery() does.
     *
     * @param list<int> $ids
     *
     * @return Page[]
     */
    public function findWithMainImageByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var Page[] $pages */
        $pages = $this->createQueryBuilder('p')
            ->leftJoin('p.parentPage', 'parent')->addSelect('parent')
            ->leftJoin('p.mainImage', 'mainImage')->addSelect('mainImage')
            ->andWhere('p.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($pages as $page) {
            if (null !== $page->id) {
                $byId[$page->id] = $page;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * Initialize the `translations` collection of every given (managed) page in
     * a single query. Rendering touches the collection on every page (hreflang,
     * language switcher), which otherwise lazy-loads it one query per page —
     * measurable at static-build scale. Hydration attaches the joined rows to
     * the already-managed instances, so the collections stay initialized even
     * after a later EntityManager::clear().
     *
     * @param Page[] $pages
     */
    public function preloadTranslations(array $pages): void
    {
        $ids = [];
        foreach ($pages as $page) {
            if (null !== $page->id) {
                $ids[] = $page->id;
            }
        }

        if ([] === $ids) {
            return;
        }

        $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')->addSelect('t')
            ->andWhere('p.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
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
        $this->andNotVariant($queryBuilder);

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

        // LOWER() rather than a bare LIKE: SQLite and a *_ci MySQL collation match
        // case-insensitively on their own, but that is the collation's doing, not a
        // guarantee — {@see Page::hasNoindex()}, the twin this must agree with, is
        // explicitly case-insensitive. `none` is there for the same reason it is
        // there: it is the robots shorthand for `noindex, nofollow`.
        $metaRobots = 'LOWER('.$alias.'.metaRobots)';

        return $queryBuilder
            ->andWhere($alias.'.metaRobots IS NULL OR ('.$metaRobots.' NOT LIKE :noi2 AND '.$metaRobots.' NOT LIKE :noi3)')
            ->setParameter('noi2', '%noindex%')
            ->setParameter('noi3', '%none%');
    }

    public function andNotRedirection(QueryBuilder $queryBuilder): QueryBuilder
    {
        $alias = $this->getRootAlias($queryBuilder);

        return $queryBuilder->andWhere($alias.'.mainContent NOT LIKE :noi')
            ->setParameter('noi', 'Location:%');
    }

    /**
     * Exclude slugs already linked on the page being rendered (see LinkCollectorService).
     * Filtering here rather than on the hydrated result keeps the limit meaningful: the
     * query returns `max` pages that survived the exclusion, instead of `max` pages minus
     * the excluded ones.
     *
     * @param string[] $slugs
     */
    public function andNotSlug(QueryBuilder $queryBuilder, array $slugs): QueryBuilder
    {
        if ([] === $slugs) {
            return $queryBuilder;
        }

        return $queryBuilder->andWhere($this->getRootAlias($queryBuilder).'.slug NOT IN (:excludedSlugs)')
            ->setParameter('excludedSlugs', $slugs);
    }

    /**
     * Exclude variant pages (they consolidate onto their master: kept out of
     * sitemap, feed and search index).
     */
    public function andNotVariant(QueryBuilder $queryBuilder): QueryBuilder
    {
        $alias = $this->getRootAlias($queryBuilder);

        return $queryBuilder->andWhere($alias.'.variantOf IS NULL');
    }

    /**
     * Whether the host has at least one variant page. Memoized per host (reset on
     * EM clear) so the variant link filter can skip work entirely on sites without
     * variants — the common case.
     */
    public function hasVariant(string $host): bool
    {
        return $this->hasVariantCache[$host] ??= null !== $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.variantOf IS NOT NULL')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
