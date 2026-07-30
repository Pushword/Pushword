<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\ContentTrigger;
use Pushword\Newsletter\Entity\ContentTriggerLog;

/**
 * @extends ServiceEntityRepository<ContentTriggerLog>
 */
class ContentTriggerLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentTriggerLog::class);
    }

    /**
     * The rows whose campaign is among the given ids. The caller passes the
     * campaigns that have not gone out yet, so the "should this still be sent?"
     * pass reads a handful of rows rather than the whole history.
     *
     * @param list<int> $campaignIds
     *
     * @return list<ContentTriggerLog>
     */
    public function findForCampaigns(array $campaignIds): array
    {
        if ([] === $campaignIds) {
            return [];
        }

        /** @var list<ContentTriggerLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->andWhere('l.campaignId IN (:campaigns)')
            ->setParameter('campaigns', $campaignIds)
            ->getQuery()
            ->getResult();

        return $logs;
    }

    public function countFor(ContentTrigger $trigger): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.trigger = :trigger')
            ->setParameter('trigger', $trigger)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
