<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Enum\ContactTransition;
use Pushword\Newsletter\Enum\RecipientState;

/**
 * @phpstan-type CampaignStatistics array{id: int, subject: string, audience: string|null, audienceId: int|null, date: string|null, status: string, tracking: bool, recipients: int, sent: int, failed: int, bounced: int, unsubscribed: int, clicks: int, clickers: int, pending: int, skipped: int, clickRate: float|null, bounceRate: float|null, unsubscribeRate: float|null}
 * @phpstan-type CampaignRow array{id: int|string, subject: string, audience: string|null, audience_id: int|string|null, date: string|null, status: string, tracking: bool|int|string|null, recipients: int|string, sent: int|string, failed: int|string, bounced: int|string, unsubscribed: int|string, clicks: int|string}
 */
final readonly class StatisticsRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<CampaignStatistics> */
    public function campaigns(DateTimeImmutable $from, DateTimeImmutable $until, ?int $audienceId): array
    {
        $query = $this->campaignQuery()
            ->andWhere('COALESCE(delivery.first_sent_at, c.sent_at) >= :from')
            ->andWhere('COALESCE(delivery.first_sent_at, c.sent_at) < :until')
            ->setParameter('from', $from->format('Y-m-d H:i:s'))
            ->setParameter('until', $until->format('Y-m-d H:i:s'));

        if (null !== $audienceId) {
            $query->andWhere('c.audience_id = :audience')->setParameter('audience', $audienceId);
        }

        return $this->hydrateCampaigns($query);
    }

    /** @return CampaignStatistics|null */
    public function campaign(int $id): ?array
    {
        return $this->hydrateCampaigns($this->campaignQuery()->andWhere('c.id = :id')->setParameter('id', $id))[0] ?? null;
    }

    /**
     * Count each contact once per campaign, including when aggregating campaigns.
     * The denominator is transport handoffs, never an inferred delivery count.
     *
     * @param list<CampaignStatistics> $campaigns
     *
     * @return array<string, int|float|null>
     */
    public function totals(array $campaigns): array
    {
        $totals = array_fill_keys(['recipients', 'sent', 'failed', 'bounced', 'unsubscribed', 'clicks', 'clickers', 'pending', 'skipped'], 0);
        $tracking = false;
        foreach ($campaigns as $campaign) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $campaign[$key];
            }

            $tracking = $tracking || $campaign['tracking'] || $campaign['clicks'] > 0;
        }

        return [
            ...$totals,
            'campaigns' => \count($campaigns),
            'clickRate' => $tracking ? $this->rate($totals['clickers'], $totals['sent']) : null,
            'bounceRate' => $this->rate($totals['bounced'], $totals['sent']),
            'unsubscribeRate' => $this->rate($totals['unsubscribed'], $totals['sent']),
        ];
    }

    /** @return array<string, int> */
    public function contacts(?int $audienceId): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('status', 'COUNT(*) AS total', 'COUNT(email) AS with_email')
            ->from('newsletter_contact')
            ->groupBy('status');
        if (null !== $audienceId) {
            $query->andWhere('audience_id = :audience')->setParameter('audience', $audienceId);
        }

        $counts = array_fill_keys([...array_column(ContactStatus::cases(), 'value'), 'mailable'], 0);
        /** @var list<array{status: string, total: int|string, with_email: int|string}> $rows */
        $rows = $query->executeQuery()->fetchAllAssociative();
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
            if (ContactStatus::Subscribed->value === $row['status']) {
                $counts['mailable'] = (int) $row['with_email'];
            }
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function subscriptionActivity(DateTimeImmutable $from, DateTimeImmutable $until, ?int $audienceId): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('e.transition', 'COUNT(*) AS total')
            ->from('newsletter_contact_event', 'e')
            ->innerJoin('e', 'newsletter_contact', 'c', 'c.id = e.contact_id')
            ->where('e.occurred_at >= :from AND e.occurred_at < :until')
            ->setParameter('from', $from->format('Y-m-d H:i:s'))
            ->setParameter('until', $until->format('Y-m-d H:i:s'))
            ->groupBy('e.transition');
        if (null !== $audienceId) {
            $query->andWhere('c.audience_id = :audience')->setParameter('audience', $audienceId);
        }

        $counts = array_fill_keys(array_column(ContactTransition::cases(), 'value'), 0);
        /** @var list<array{transition: string, total: int|string}> $rows */
        $rows = $query->executeQuery()->fetchAllAssociative();
        foreach ($rows as $row) {
            $counts[$row['transition']] = (int) $row['total'];
        }

        return $counts;
    }

    private function campaignQuery(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'c.id',
                'c.subject',
                'c.audience_id',
                'a.name AS audience',
                '(SELECT COUNT(m.id) FROM newsletter_delivery_message m WHERE m.campaign_id = c.id AND m.accepted_at IS NOT NULL AND m.tracking_allowed = TRUE) AS tracking',
                'c.status',
                'c.recipient_count AS recipients',
                'c.sent_count AS sent',
                'c.failed_count AS failed',
                'c.bounce_count AS bounced',
                'c.unsub_count AS unsubscribed',
                'c.click_count AS clicks',
                'COALESCE(delivery.first_sent_at, c.sent_at) AS date'
            )
            ->from('newsletter_campaign', 'c')
            ->leftJoin('c', 'newsletter_audience', 'a', 'a.id = c.audience_id')
            ->leftJoin('c', '(SELECT campaign_id, MIN(sent_at) AS first_sent_at FROM newsletter_campaign_recipient GROUP BY campaign_id)', 'delivery', 'delivery.campaign_id = c.id')
            ->orderBy('date', 'DESC')
            ->addOrderBy('c.id', 'DESC');
    }

    /** @return list<CampaignStatistics> */
    private function hydrateCampaigns(QueryBuilder $query): array
    {
        /** @var list<CampaignRow> $rows */
        $rows = $query->executeQuery()->fetchAllAssociative();
        if ([] === $rows) {
            return [];
        }

        $ids = array_column($rows, 'id');
        /** @var array<int, int|string> $clickers */
        $clickers = $this->connection->executeQuery(
            'SELECT campaign_id, COUNT(DISTINCT contact_id) FROM newsletter_click_event WHERE campaign_id IN (?) GROUP BY campaign_id',
            [$ids],
            [ArrayParameterType::INTEGER],
        )->fetchAllKeyValue();
        /** @var list<array{campaign_id: int|string, state: string, total: int|string}> $states */
        $states = $this->connection->executeQuery(
            'SELECT campaign_id, state, COUNT(*) AS total FROM newsletter_campaign_recipient WHERE campaign_id IN (?) GROUP BY campaign_id, state',
            [$ids],
            [ArrayParameterType::INTEGER],
        )->fetchAllAssociative();
        $pending = [];
        $skipped = [];
        foreach ($states as $state) {
            if (RecipientState::Pending->value === $state['state']) {
                $pending[$state['campaign_id']] = (int) $state['total'];
            } elseif (RecipientState::Skipped->value === $state['state']) {
                $skipped[$state['campaign_id']] = (int) $state['total'];
            }
        }

        $campaigns = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $sent = (int) $row['sent'];
            $clicks = (int) $row['clicks'];
            $unique = (int) ($clickers[$id] ?? 0);
            $tracking = (bool) $row['tracking'];
            $campaigns[] = [
                'id' => $id,
                'subject' => $row['subject'],
                'audienceId' => null !== $row['audience_id'] ? (int) $row['audience_id'] : null,
                'audience' => $row['audience'],
                'date' => $row['date'],
                'status' => $row['status'],
                'tracking' => $tracking,
                'recipients' => (int) $row['recipients'],
                'sent' => $sent,
                'failed' => (int) $row['failed'],
                'bounced' => (int) $row['bounced'],
                'unsubscribed' => (int) $row['unsubscribed'],
                'clicks' => $clicks,
                'clickers' => $unique,
                'pending' => $pending[$id] ?? 0,
                'skipped' => $skipped[$id] ?? 0,
                'clickRate' => $tracking || $clicks > 0 ? $this->rate($unique, $sent) : null,
                'bounceRate' => $this->rate((int) $row['bounced'], $sent),
                'unsubscribeRate' => $this->rate((int) $row['unsubscribed'], $sent),
            ];
        }

        return $campaigns;
    }

    private function rate(int $count, int $sent): ?float
    {
        return 0 === $sent ? null : round(100 * $count / $sent, 2);
    }
}
