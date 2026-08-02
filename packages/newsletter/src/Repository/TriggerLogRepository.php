<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\TriggerLog;

/**
 * @extends ServiceEntityRepository<TriggerLog>
 */
class TriggerLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TriggerLog::class);
    }

    /**
     * The subject ids an automation has handled, as a DQL subselect.
     *
     * A subselect and not a list: a source filters its own table with it, so
     * what has been handled never crosses into PHP, and a rule matching a
     * ten-year archive costs the same as one matching yesterday. The parameter
     * it leaves open is `:automation`, which the caller binds.
     */
    public function handledSubjectsDql(): string
    {
        return $this->createQueryBuilder('handled')
            ->select('handled.subjectId')
            ->andWhere('handled.automation = :automation')
            ->getDQL();
    }

    public function findFor(Automation $automation, int $subjectId): ?TriggerLog
    {
        return $this->findOneBy(['automation' => $automation, 'subjectId' => $subjectId]);
    }

    public function countFor(Automation $automation): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.automation = :automation')
            ->setParameter('automation', $automation)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
