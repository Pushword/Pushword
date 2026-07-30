<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\ContentTrigger;

/**
 * @extends ServiceEntityRepository<ContentTrigger>
 */
class ContentTriggerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentTrigger::class);
    }

    /** @return list<ContentTrigger> */
    public function findEnabled(): array
    {
        /** @var list<ContentTrigger> $triggers */
        $triggers = $this->createQueryBuilder('t')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $triggers;
    }
}
