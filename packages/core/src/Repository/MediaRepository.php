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

        return array_column($results, 'mimeType');
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
}
