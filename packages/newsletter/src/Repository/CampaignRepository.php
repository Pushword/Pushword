<?php

namespace Pushword\Newsletter\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Enum\CampaignStatus;

/**
 * @extends ServiceEntityRepository<Campaign>
 */
class CampaignRepository extends ServiceEntityRepository
{
    /** Not armed yet: nothing has been frozen and nobody has received anything. */
    private const array PENDING = [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value];

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
     * The campaigns an automation produced that have not gone out yet: still
     * editable, still cancellable, and still worth asking the source about.
     *
     * @return list<Campaign>
     */
    public function findPendingTriggered(): array
    {
        /** @var list<Campaign> $campaigns */
        $campaigns = $this->createQueryBuilder('c')
            ->andWhere('c.status IN (:pending)')
            ->andWhere('c.automation IS NOT NULL')
            ->setParameter('pending', self::PENDING)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $campaigns;
    }

    /**
     * Has any campaign for this subject already been armed? Past that point the
     * subject has been announced, whatever became of the steps after it.
     */
    public function hasArmed(Automation $automation, int $subjectId): bool
    {
        $count = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.automation = :automation')
            ->andWhere('c.triggerSubjectId = :subjectId')
            ->andWhere('c.status NOT IN (:pending)')
            ->setParameter('automation', $automation)
            ->setParameter('subjectId', $subjectId)
            ->setParameter('pending', self::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
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
