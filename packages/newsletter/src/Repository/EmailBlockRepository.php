<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\EmailBlock;

/** @extends ServiceEntityRepository<EmailBlock> */
class EmailBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailBlock::class);
    }

    /** @return list<EmailBlock> */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('b')->where('LOWER(b.name) LIKE :query')->setParameter('query', '%'.mb_strtolower($query).'%')
            ->orderBy('b.name', 'ASC')->addOrderBy('b.id', 'ASC')->setMaxResults(25)->getQuery()->getResult();
    }
}
