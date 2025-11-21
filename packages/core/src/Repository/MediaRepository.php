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
    #[Required]
    public PageRepository $pageRepository;

    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Media::class);
    }

    /**
     * @return array{mimeType: string[], ratioLabel: string[]}
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

        return [
            'mimeType' => $mimeTypes,
            'ratioLabel' => $ratioLabels,
        ];
    }

    public function findDuplicate(Media $media): ?Media
    {
        $duplicates = $this->findBy(['hash' => $media->getHash()]);

        foreach ($duplicates as $duplicate) {
            if ($media->getId() !== $duplicate->getId()) {
                return $duplicate;
            }
        }

        return null;
    }

    public function getOldStoreIn(): ?string
    {
        $oldestMedia = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (! $oldestMedia instanceof Media) {
            return null;
        }

        $storeIn = $oldestMedia->getStoreIn();
        $storeInParts = explode('/media/', $storeIn, 2);

        return $storeInParts[0];
    }

    /** @return array<string> */
    public function getAllMedia(): array
    {
        /** @var string[] $medias */
        $medias = $this->createQueryBuilder('m')
            ->select('m.media AS media')
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
            ->setMaxResults(30000); // some kind of arbitrary parapet

        /** @var array{tags: string[]}[] */
        $tags = $queryBuilder->getQuery()->getResult();

        foreach ($tags as $entity) {
            $allTags = array_merge($allTags, $entity['tags']);
        }

        return array_values(array_unique($allTags));
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
            $exp->like($alias.'.name', $likeFilterValue),
            $exp->like($alias.'.nameSearch', $likeNormalizedFilterValue),
            $exp->like($alias.'.media', $likeFilterValue),
            $exp->like($alias.'.names', $likeFilterValue),
            $exp->like($alias.'.tags', $likeFilterValue),
        );
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
