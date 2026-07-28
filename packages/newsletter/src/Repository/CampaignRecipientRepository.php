<?php

namespace Pushword\Newsletter\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\RecipientState;

/**
 * @extends ServiceEntityRepository<CampaignRecipient>
 */
class CampaignRecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CampaignRecipient::class);
    }

    /** @return list<CampaignRecipient> */
    public function findPending(Campaign $campaign, int $limit): array
    {
        /** @var list<CampaignRecipient> $recipients */
        $recipients = $this->createQueryBuilder('r')
            ->andWhere('r.campaign = :campaign')
            ->andWhere('r.state = :pending')
            ->setParameter('campaign', $campaign)
            ->setParameter('pending', RecipientState::Pending->value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $recipients;
    }

    public function countPending(Campaign $campaign): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.campaign = :campaign')
            ->andWhere('r.state = :pending')
            ->setParameter('campaign', $campaign)
            ->setParameter('pending', RecipientState::Pending->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** When the campaign last handed a mail to the transport — the cadence anchor. */
    public function lastSentAt(Campaign $campaign): ?DateTimeImmutable
    {
        $result = $this->createQueryBuilder('r')
            ->select('MAX(r.sentAt)')
            ->andWhere('r.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->getQuery()
            ->getSingleScalarResult();

        return \is_string($result) ? new DateTimeImmutable($result) : null;
    }

    /**
     * Contacts already materialised for this campaign, so re-arming cannot
     * duplicate a row.
     *
     * @return list<int>
     */
    public function contactIds(Campaign $campaign): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.contact) AS contactId')
            ->andWhere('r.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): int => $row['contactId'], $rows);
    }

    /**
     * Every campaign row of a contact that has already gone out — the anchor a
     * later unsubscribe or bounce is attributed to.
     *
     * @return list<CampaignRecipient>
     */
    public function findSentFor(Contact $contact): array
    {
        /** @var list<CampaignRecipient> $recipients */
        $recipients = $this->createQueryBuilder('r')
            ->andWhere('r.contact = :contact')
            ->andWhere('r.state = :sent')
            ->setParameter('contact', $contact)
            ->setParameter('sent', RecipientState::Sent->value)
            ->orderBy('r.sentAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $recipients;
    }
}
