<?php

namespace Pushword\Newsletter\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Enum\CampaignStatus;

/**
 * @extends ServiceEntityRepository<Campaign>
 */
class CampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Campaign::class);
    }

    /**
     * Scheduled campaigns whose date has passed — the tick materialises them.
     *
     * @return list<Campaign>
     */
    public function findDue(DateTimeImmutable $now): array
    {
        /** @var list<Campaign> $campaigns */
        $campaigns = $this->createQueryBuilder('c')
            ->andWhere('c.status = :scheduled')
            ->andWhere('c.scheduledAt <= :now')
            ->setParameter('scheduled', CampaignStatus::Scheduled->value)
            ->setParameter('now', $now)
            ->orderBy('c.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $campaigns;
    }

    /**
     * Campaigns that have not gone out yet: still editable, still cancellable.
     *
     * @return list<int>
     */
    public function findPendingIds(): array
    {
        /** @var list<array{id: int}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.id')
            ->andWhere('c.status IN (:pending)')
            ->setParameter('pending', [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value])
            ->getQuery()
            ->getResult();

        return array_column($rows, 'id');
    }

    /** @return list<Campaign> */
    public function findSending(): array
    {
        /** @var list<Campaign> $campaigns */
        $campaigns = $this->createQueryBuilder('c')
            ->andWhere('c.status = :sending')
            ->setParameter('sending', CampaignStatus::Sending->value)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $campaigns;
    }
}
