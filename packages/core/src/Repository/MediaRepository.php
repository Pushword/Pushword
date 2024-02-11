<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\MediaInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @extends ServiceEntityRepository<MediaInterface>
 *
 * @implements Selectable<int, MediaInterface>
 * @implements ObjectRepository<MediaInterface>
 */
#[AutoconfigureTag('doctrine.repository_service')]
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

        /** @var array{mimeType: string} */
        $results = $queryBuilder->getQuery()->getResult();

        return array_column($results, 'mimeType');
    }

    public function findDuplicate(MediaInterface $media): ?MediaInterface
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
