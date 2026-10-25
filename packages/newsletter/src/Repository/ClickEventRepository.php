<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Statistics\ReportQuery;

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
    public function byUrl(Campaign $campaign, ?ReportQuery $selection = null): array
    {
        if (null !== $selection) {
            return $this->observedUrls($campaign, $selection);
        }

        return $this->createQueryBuilder('c')
            ->select('c.url AS url', 'COUNT(c.id) AS clicks', 'COUNT(DISTINCT c.contact) AS contacts')
            ->andWhere('c.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->groupBy('c.url')
            ->orderBy('clicks', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{url: string, clicks: int, contacts: int}> */
    private function observedUrls(Campaign $campaign, ReportQuery $selection): array
    {
        $query = $this->createQueryBuilder('c')->addSelect('m')->leftJoin('c.message', 'm')
            ->where('c.campaign = :campaign')->setParameter('campaign', $campaign);
        /** @var list<ClickEvent> $events */
        $events = $query->getQuery()->getResult();
        $urls = [];
        foreach ($events as $click) {
            if (null !== $selection->windowDays && null === $click->message) {
                continue;
            }

            if (! $selection->observes($click->message->acceptedAt ?? $click->clickedAt, $click->clickedAt)) {
                continue;
            }

            $urls[$click->url]['clicks'] = ($urls[$click->url]['clicks'] ?? 0) + 1;
            $urls[$click->url]['contacts'][$click->contact->id ?? 0] = true;
        }

        $results = [];
        foreach ($urls as $url => $counts) {
            $results[] = ['url' => $url, 'clicks' => $counts['clicks'], 'contacts' => \count($counts['contacts'])];
        }

        return $results;
    }
}
