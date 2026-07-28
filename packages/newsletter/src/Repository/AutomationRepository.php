<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Automation;

/**
 * @extends ServiceEntityRepository<Automation>
 */
class AutomationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Automation::class);
    }

    /** @return list<Automation> */
    public function findEnabled(): array
    {
        /** @var list<Automation> $automations */
        $automations = $this->createQueryBuilder('a')
            ->andWhere('a.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $automations;
    }
}
