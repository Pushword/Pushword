<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\Contact;

/**
 * @extends ServiceEntityRepository<ClickEvent>
 */
class ClickEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClickEvent::class);
    }

    /**
     * Withdrawing the consent takes the collected rows with it: the clicks were
     * only ever kept under it.
     */
    public function purgeFor(Contact $contact): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.contact = :contact')
            ->setParameter('contact', $contact)
            ->getQuery()
            ->execute();
    }

    public function countFor(Campaign $campaign, Contact $contact): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.campaign = :campaign')
            ->andWhere('c.contact = :contact')
            ->setParameter('campaign', $campaign)
            ->setParameter('contact', $contact)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The campaign's clicks per destination — which links pulled, and how many
     * different readers each one pulled.
     *
     * @return list<array{url: string, clicks: int, contacts: int}>
     */
    public function byUrl(Campaign $campaign): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.url AS url', 'COUNT(c.id) AS clicks', 'COUNT(DISTINCT c.contact) AS contacts')
            ->andWhere('c.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->groupBy('c.url')
            ->orderBy('clicks', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
