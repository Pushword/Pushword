<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;

class MediaRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
    public function getMimeTypes()
    {
        $qb = $this->createQueryBuilder('m');
        $qb->select('m.mimeType');
        $qb->groupBy('m.mimeType');
        $qb->orderBy('m.mimeType', 'ASC');

        return array_column($qb->getQuery()->getResult(), 'mimeType');
    }
}
