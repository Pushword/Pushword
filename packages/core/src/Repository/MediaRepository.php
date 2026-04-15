<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Events;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Utils\SearchNormalizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @extends ServiceEntityRepository<Media>
 *
 * @implements Selectable<int, Media>
 * @implements ObjectRepository<Media>
 *
 * @method Media[] findAll()
 */
#[AsDoctrineListener(event: Events::onClear)]
class MediaRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
    use TagsRepositoryTrait;

    public const VERSION_CACHE_KEY = 'pw.media.version';

    public const INDEX_CACHE_KEY_PREFIX = 'pw.media.filename_index.v';

    private const int INDEX_CACHE_TTL = 86400;

    #[Required]
    public PageRepository $pageRepository;

    /**
     * Lightweight filename index. Scalar rows only — enough to resolve a
     * filename to a Media id without hydrating the full entity. Built via a
     * single DQL array-hydration query, then optionally persisted to cache.app
     * across requests and invalidated by bumping a version counter on writes.
     *
     * @var array<string, array{id: int, fileName: string, fileNameHistory: list<string>}>|null
     */
    private ?array $fileNameIndexLight = null;

    private bool $warmedLight = false;

    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(service: 'cache.app')]
        private readonly ?CacheItemPoolInterface $cache = null,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug = false,
    ) {
        parent::__construct($registry, Media::class);
    }

    /**
     * Populate the lightweight filename→id index with a single scalar DQL query.
     * No Media entity hydration. Persisted across requests via cache.app keyed
     * by a per-table version counter (unless kernel.debug is true or no cache
     * pool is available, in which case the index lives for the repo instance
     * only and is rebuilt per request).
     */
    public function warmupFileNameIndexLight(): void
    {
        if ($this->warmedLight) {
            return;
        }

        $cache = $this->debug ? null : $this->cache;

        if (null !== $cache) {
            $indexItem = $cache->getItem(self::INDEX_CACHE_KEY_PREFIX.$this->readVersion());
            if ($indexItem->isHit()) {
                /** @var array<string, array{id: int, fileName: string, fileNameHistory: list<string>}> $index */
                $index = $indexItem->get();
                $this->fileNameIndexLight = $index;
                $this->warmedLight = true;

                return;
            }

            $this->fileNameIndexLight = $this->buildFileNameIndex();
            $this->warmedLight = true;

            $indexItem->set($this->fileNameIndexLight);
            $indexItem->expiresAfter(self::INDEX_CACHE_TTL);
            $cache->save($indexItem);

            return;
        }

        $this->fileNameIndexLight = $this->buildFileNameIndex();
        $this->warmedLight = true;
    }

    /**
     * @return array<string, array{id: int, fileName: string, fileNameHistory: list<string>}>
     */
    private function buildFileNameIndex(): array
    {
        /** @var list<array{id: int|string, fileName: string, fileNameHistory: mixed}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.id AS id, m.fileName AS fileName, m.fileNameHistory AS fileNameHistory')
            ->getQuery()
            ->getArrayResult();

        $index = [];
        foreach ($rows as $row) {
            $history = $row['fileNameHistory'];
            if (! \is_array($history)) {
                $history = [];
            }

            /** @var list<string> $historyList */
            $historyList = array_values(array_filter($history, \is_string(...)));

            $index[$row['fileName']] = [
                'id' => (int) $row['id'],
                'fileName' => $row['fileName'],
                'fileNameHistory' => $historyList,
            ];
        }

        return $index;
    }

    private function readVersion(): int
    {
        if (null === $this->cache) {
            return 0;
        }

        $item = $this->cache->getItem(self::VERSION_CACHE_KEY);
        if (! $item->isHit()) {
            return 0;
        }

        $value = $item->get();

        return \is_int($value) ? $value : 0;
    }

    /**
     * Increment the per-table version counter. The next lookup on any
     * repository instance rebuilds the index. Safe to call from lifecycle
     * listeners on every Media write. Non-atomic across parallel requests —
     * worst case is a duplicate rebuild.
     */
    public function bumpVersion(): void
    {
        if (null !== $this->cache) {
            $item = $this->cache->getItem(self::VERSION_CACHE_KEY);
            $value = $item->isHit() ? $item->get() : null;
            $item->set((\is_int($value) ? $value : 0) + 1);
            $this->cache->save($item);
        }

        $this->resetFileNameIndexLight();
    }

    public function resetFileNameIndexLight(): void
    {
        $this->fileNameIndexLight = null;
        $this->warmedLight = false;
    }

    public function isWarmedLight(): bool
    {
        return $this->warmedLight;
    }

    /**
     * Called automatically by the Doctrine onClear event so CLI batch paths
     * that call EntityManager::clear() see a fresh rebuild on next lookup.
     */
    public function onClear(): void
    {
        $this->resetFileNameIndexLight();
    }

    public function loadMedias(): void
    {
        $this->warmupFileNameIndexLight();
    }

    public function resetMediasByFileNameCache(): void
    {
        $this->resetFileNameIndexLight();
    }

    public function findOneByFileName(string $fileName): ?Media
    {
        $this->warmupFileNameIndexLight();

        $entry = $this->fileNameIndexLight[$fileName] ?? null;
        if (null === $entry) {
            return null;
        }

        return $this->find($entry['id']);
    }

    /**
     * Find media by filename, falling back to filename history if not found.
     */
    public function findOneByFileNameOrHistory(string $fileName): ?Media
    {
        $this->warmupFileNameIndexLight();

        $entry = $this->fileNameIndexLight[$fileName] ?? null;
        if (null !== $entry) {
            return $this->find($entry['id']);
        }

        foreach ($this->fileNameIndexLight ?? [] as $candidate) {
            if (\in_array($fileName, $candidate['fileNameHistory'], true)) {
                return $this->find($candidate['id']);
            }
        }

        return null;
    }

    /**
     * Check if a filename is already used by another media (current or historical).
     * Returns the media that uses this filename, or null if available.
     *
     * @param int|null $excludeId ID of media to exclude from check (for updates)
     */
    public function isFileNameUsed(string $fileName, ?int $excludeId = null): ?Media
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.fileName = :currentName OR m.fileNameHistory LIKE :historyName')
            ->setParameter('currentName', $fileName)
            ->setParameter('historyName', '%"'.$fileName.'"%');

        if (null !== $excludeId) {
            $qb->andWhere('m.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        /** @var Media|null */
        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    /**
     * @return array{mimeType: string[], ratioLabel: string[], dimensions: string[]}
     */
    public function getMimeTypesAndRatio(): array
    {
        $mimeTypesResults = $this->createQueryBuilder('m')
            ->select('DISTINCT m.mimeType AS mimeType')
            ->orderBy('m.mimeType', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $ratioLabelsResults = $this->createQueryBuilder('m')
            ->select('DISTINCT m.imageData.ratioLabel AS ratioLabel')
            ->orderBy('m.imageData.ratioLabel', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $dimensionsResults = $this->createQueryBuilder('m')
            ->select('DISTINCT m.imageData.width AS width, m.imageData.height AS height')
            ->where('m.imageData.width IS NOT NULL')
            ->andWhere('m.imageData.height IS NOT NULL')
            ->orderBy('m.imageData.width', 'ASC')
            ->addOrderBy('m.imageData.height', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $normalizedMimeTypes = array_map(
            static fn (mixed $mimeType): ?string => 'image/jpg' === $mimeType ? 'image/jpeg'
            : (is_string($mimeType) ? $mimeType : null),
            array_column($mimeTypesResults, 'mimeType'),
        );

        $mimeTypes = array_values(
            array_unique(
                array_filter($normalizedMimeTypes, static fn (mixed $mimeType): bool => null !== $mimeType),
            ),
        );

        $ratioLabels = array_values(
            array_unique(
                array_filter(
                    array_column($ratioLabelsResults, 'ratioLabel'),
                    static fn (mixed $ratioLabel): bool => is_string($ratioLabel) && '' !== $ratioLabel,
                ),
            ),
        );

        $dimensions = array_values(
            array_filter(
                array_unique(
                    array_map(
                        static function (mixed $dimensionRow): ?string {
                            if (! \is_array($dimensionRow)) {
                                return null;
                            }

                            $width = $dimensionRow['width'] ?? null;
                            $height = $dimensionRow['height'] ?? null;

                            if (! \is_int($width) && ! (is_string($width) && ctype_digit($width))) {
                                return null;
                            }

                            if (! \is_int($height) && ! (is_string($height) && ctype_digit($height))) {
                                return null;
                            }

                            return sprintf('%d×%d', (int) $width, (int) $height);
                        },
                        $dimensionsResults,
                    ),
                ),
                \is_string(...),
            ),
        );

        return [
            'mimeType' => $mimeTypes,
            'ratioLabel' => $ratioLabels,
            'dimensions' => $dimensions,
        ];
    }

    public function findDuplicate(Media $media): ?Media
    {
        $duplicates = $this->findBy(['hash' => $media->getHash()]);

        foreach ($duplicates as $duplicate) {
            if ($media->id !== $duplicate->id) {
                return $duplicate;
            }
        }

        return null;
    }

    /** @return Media[][] each inner array is a group of duplicate medias (same hash), sorted by id */
    public function findDuplicateGroups(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $duplicateHashes = $conn->executeQuery(
            'SELECT hash FROM media WHERE hash IS NOT NULL AND LENGTH(hash) > 0 GROUP BY hash HAVING COUNT(*) > 1',
        )->fetchFirstColumn();

        if ([] === $duplicateHashes) {
            return [];
        }

        $groups = [];
        foreach ($duplicateHashes as $hash) {
            // Normalize resource streams to strings (PDO may return BLOBs as resources)
            if (\is_resource($hash)) {
                $hash = stream_get_contents($hash);
            }

            if (! \is_string($hash)) {
                continue;
            }

            if ('' === $hash) {
                continue;
            }

            /** @var Media[] $medias */
            $medias = $this->findBy(['hash' => $hash], ['id' => 'ASC']);
            if (\count($medias) > 1) {
                $groups[] = $medias;
            }
        }

        return $groups;
    }

    /** @return array<string> */
    public function getAllMedia(): array
    {
        /** @var string[] $medias */
        $medias = $this->createQueryBuilder('m')
            ->select('m.fileName AS fileName')
            ->getQuery()
            ->getSingleColumnResult();

        return $medias;
    }

    /**
     * @return string[]
     */
    public function getMediaTags(): array
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->select('m.tags')
            ->setMaxResults(30000);

        /** @var array{tags: string[]}[] */
        $mediaTags = $queryBuilder->getQuery()->getResult();

        return $this->flattenTags($mediaTags);
    }

    /**
     * @return string[]
     */
    public function getAllTags(): array
    {
        return array_values(array_unique([...$this->pageRepository->getAllTags(), ...$this->getMediaTags()]));
    }

    public function getExprToFilterMedia(string $alias, string $filterValue): Orx
    {
        $exp = new Expr();
        $normalizedFilterValue = SearchNormalizer::normalize($filterValue);
        if ('' === $normalizedFilterValue) {
            $normalizedFilterValue = $filterValue;
        }

        $likeFilterValue = $exp->literal('%'.$filterValue.'%');
        $likeNormalizedFilterValue = $exp->literal('%'.$normalizedFilterValue.'%');

        return $exp->orX(
            $exp->like($alias.'.fileName', $likeFilterValue),
            $exp->like($alias.'.fileNameHistory', $likeFilterValue),
            $exp->like($alias.'.alt', $likeFilterValue),
            $exp->like($alias.'.altSearch', $likeNormalizedFilterValue),
            $exp->like($alias.'.alts', $likeFilterValue),
            $exp->like($alias.'.tags', $likeFilterValue),
        );
    }

    public function findOneBySearch(string $search): ?Media
    {
        $normalizedSearch = SearchNormalizer::normalize($search);
        if ('' === $normalizedSearch) {
            $normalizedSearch = $search;
        }

        /** @var Media|null */
        return $this->createQueryBuilder('m')
            ->where($this->getExprToFilterMedia('m', $search))
            ->setParameter('search', '%'.$search.'%')
            ->setParameter('normalizedSearch', '%'.$normalizedSearch.'%')
            ->addOrderBy(
                'CASE'
                .' WHEN m.fileName LIKE :search THEN 1'
                .' WHEN m.fileNameHistory LIKE :search THEN 2'
                .' WHEN m.alt LIKE :search THEN 3'
                .' WHEN m.altSearch LIKE :normalizedSearch THEN 4'
                .' WHEN m.alts LIKE :search THEN 5'
                .' WHEN m.tags LIKE :search THEN 6'
                .' ELSE 7 END',
            )
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Media[]
     */
    public function findBySearch(string $search): array
    {
        $exp = $this->getExprToFilterMedia('m', $search);

        return $this->createQueryBuilder('m')
            ->where($exp)
            ->getQuery()
            ->getResult();
    }
}
