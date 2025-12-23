<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\Media;
use Pushword\Core\Utils\SearchNormalizer;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @extends ServiceEntityRepository<Media>
 *
 * @implements Selectable<int, Media>
 * @implements ObjectRepository<Media>
 *
 * @method Media[] findAll()
 */
class MediaRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
    use TagsRepositoryTrait;

    #[Required]
    public PageRepository $pageRepository;

    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Media::class);
    }

    /** @var array<string, Media> */
    private array $mediasByFileNameCache = [];

    public function loadMedias(): void
    {
        if ([] !== $this->mediasByFileNameCache) {
            return;
        }

        $medias = $this->findAll();
        foreach ($medias as $media) {
            $this->mediasByFileNameCache[$media->getFileName()] = $media;
        }
    }

    public function findOneByFileName(string $fileName): ?Media
    {
        if ([] === $this->mediasByFileNameCache) {
            $this->loadMedias();
        }

        return $this->mediasByFileNameCache[$fileName] ?? null;
    }

    /**
     * Find media by filename, falling back to filename history if not found.
     */
    public function findOneByFileNameOrHistory(string $fileName): ?Media
    {
        // First try exact filename match
        $media = $this->findOneByFileName($fileName);
        if (null !== $media) {
            return $media;
        }

        // Fallback: search in fileNameHistory (JSON contains)
        /** @var Media|null */
        return $this->createQueryBuilder('m')
            ->where('m.fileNameHistory LIKE :fileName')
            ->setParameter('fileName', '%"'.$fileName.'"%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if a filename is already used by another media (current or historical).
     * Returns the media that uses this filename, or null if available.
     *
     * @param int|null $excludeId ID of media to exclude from check (for updates)
     */
    public function isFileNameUsed(string $fileName, ?int $excludeId = null): ?Media
    {
        // Check current filenames
        $qb = $this->createQueryBuilder('m')
            ->where('m.fileName = :fileName')
            ->setParameter('fileName', $fileName);

        if (null !== $excludeId) {
            $qb->andWhere('m.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        /** @var Media|null $result */
        $result = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if (null !== $result) {
            return $result;
        }

        // Check historical filenames
        $qb = $this->createQueryBuilder('m')
            ->where('m.fileNameHistory LIKE :fileName')
            ->setParameter('fileName', '%"'.$fileName.'"%');

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
            ->select('DISTINCT m.ratioLabel AS ratioLabel')
            ->orderBy('m.ratioLabel', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $dimensionsResults = $this->createQueryBuilder('m')
            ->select('DISTINCT m.width AS width, m.height AS height')
            ->where('m.width IS NOT NULL')
            ->andWhere('m.height IS NOT NULL')
            ->orderBy('m.width', 'ASC')
            ->addOrderBy('m.height', 'ASC')
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
        );

        $dimensions = array_values(
            array_filter(
                $dimensions,
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
    public function getAllTags(): array
    {
        $allTags = $this->pageRepository->getAllTags();

        $queryBuilder = $this->createQueryBuilder('m')
            ->select('m.tags')
            ->setMaxResults(30000);

        /** @var array{tags: string[]}[] */
        $mediaTags = $queryBuilder->getQuery()->getResult();

        return array_values(array_unique([...$allTags, ...$this->flattenTags($mediaTags)]));
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
            $exp->like($alias.'.alt', $likeFilterValue),
            $exp->like($alias.'.altSearch', $likeNormalizedFilterValue),
            $exp->like($alias.'.alts', $likeFilterValue),
            $exp->like($alias.'.tags', $likeFilterValue),
        );
    }

    public function findOneBySearch(string $search): ?Media
    {
        // do each test like in getExprToFilterMedia but stoping when the first test is positive
        $normalizedSearch = SearchNormalizer::normalize($search);
        if ('' === $normalizedSearch) {
            $normalizedSearch = $search;
        }

        /** @var Media|null $result */
        $result = $this->createQueryBuilder('m')
            ->where('m.fileName LIKE :search')
            ->setParameter('search', '%'.$search.'%')
            ->orWhere('m.fileName LIKE :normalizedSearch')
            ->setParameter('normalizedSearch', '%'.$normalizedSearch.'%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $result) {
            return $result;
        }

        /** @var Media|null $result */
        $result = $this->createQueryBuilder('m')
            ->where('m.alt LIKE :search')
            ->setParameter('search', '%'.$search.'%')
            ->orWhere('m.alt LIKE :normalizedSearch')
            ->setParameter('normalizedSearch', '%'.$normalizedSearch.'%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $result) {
            return $result;
        }

        /** @var Media|null $result */
        $result = $this->createQueryBuilder('m')
            ->where('m.altSearch LIKE :normalizedSearch')
            ->setParameter('normalizedSearch', '%'.$normalizedSearch.'%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $result) {
            return $result;
        }

        /** @var Media|null $result */
        $result = $this->createQueryBuilder('m')
            ->where('m.alts LIKE :search')
            ->setParameter('search', '%'.$search.'%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $result) {
            return $result;
        }

        /** @var Media|null $result */
        $result = $this->createQueryBuilder('m')
            ->where('m.tags LIKE :search')
            ->setParameter('search', '%'.$search.'%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
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
