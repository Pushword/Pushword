<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\MediaInterface;

/**
 * @extends ServiceEntityRepository<MediaInterface>
 * @implements Selectable<int, MediaInterface>
 * @implements ObjectRepository<MediaInterface>
 */
class MediaRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
    /**
     * @return string[]
     */
    public function getMimeTypes(): array
    {
        $queryBuilder = $this->createQueryBuilder('m');
        $queryBuilder->select('m.mimeType');
        $queryBuilder->groupBy('m.mimeType');
        $queryBuilder->orderBy('m.mimeType', Criteria::ASC);

        return array_column($queryBuilder->getQuery()->getResult(), 'mimeType'); // @phpstan-ignore-line
    }
}
