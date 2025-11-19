<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\Media;

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
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Media::class);
    }

    /**
     * @return string[]
     */
    public function getMimeTypes(): array
    {
        $queryBuilder = $this->createQueryBuilder('m');
        $queryBuilder->select('m.mimeType');
        $queryBuilder->groupBy('m.mimeType');
        $queryBuilder->orderBy('m.mimeType', 'ASC');

        $results = $queryBuilder->getQuery()->getResult();

        $mimeTypes = array_column($results, 'mimeType');

        // Normalize image/jpg to image/jpeg and remove duplicates
        $normalized = array_map(
            fn (?string $mimeType): ?string => 'image/jpg' === $mimeType ? 'image/jpeg' : $mimeType,
            $mimeTypes
        );

        // Filter out null values and remove duplicates
        return array_values(array_unique(array_filter($normalized, fn (?string $mimeType): bool => null !== $mimeType)));
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
}
