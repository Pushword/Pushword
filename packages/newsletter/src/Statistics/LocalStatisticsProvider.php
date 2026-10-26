<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Pushword\Newsletter\Entity\AutomationDelivery;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\ContactEvent;
use Pushword\Newsletter\Entity\DeliveryEvent;
use Pushword\Newsletter\Entity\DeliveryMessage;
use Pushword\Newsletter\Enum\DeliveryEventKind;
use Pushword\Newsletter\Event\QualifyDeliveryCoverage;
use Pushword\Newsletter\Repository\StatisticsRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/** @phpstan-import-type CampaignStatistics from StatisticsRepository */
final readonly class LocalStatisticsProvider implements StatisticsProvider
{
    public function __construct(private EntityManagerInterface $entityManager, private StatisticsRepository $statistics, private UrlGeneratorInterface $urls, private ?EventDispatcherInterface $events = null)
    {
    }

    public function reports(ReportQuery $query): iterable
    {
        $reports = [];
        foreach ([$this->currentReports($query), $this->legacyAutomations($query)] as $selection) {
            foreach ($selection as $report) {
                $previous = $reports[$report->id] ?? null;
                if (null !== $previous) {
                    $metrics = [];
                    foreach (Report::METRICS as $name) {
                        $metrics[$name] = Metric::sum([$previous->metric($name), $report->metric($name)]);
                    }

                    $report = new Report(
                        $previous->source,
                        $previous->id,
                        $previous->label,
                        $previous->managementUrl,
                        $previous->date,
                        $metrics,
                        $previous->kind,
                        $previous->qualificationMethod,
                        $previous->trackingScope,
                        $previous->mature && $report->mature,
                        $previous->ratesAvailable && $report->ratesAvailable
                    );
                }

                $reports[$report->id] = $report;
            }
        }

        yield from $reports;
    }

    /** @return iterable<Report> */
    private function currentReports(ReportQuery $query): iterable
    {
        $campaigns = 'sent' === $query->basis && 'campaign' === $query->groupBy
            ? $this->statistics->campaigns($query->from, $query->until, $query->audienceId) : [];
        $messages = $this->messages($query, array_column($campaigns, 'id'));
        $groups = [];
        foreach ($messages as $message) {
            if ('campaign' !== $query->groupBy && null === $message->automation) {
                continue;
            }

            $groups[$this->groupId($message, $query)][] = $message;
        }

        $coverages = [];
        foreach ($groups as $id => $group) {
            $coverages[$id] = $this->deliveryCoverage($group, $query);
        }

        foreach ($campaigns as $campaign) {
            $coverages['campaign:'.$campaign['id']] ??= $this->deliveryCoverage([], $query);
        }

        // Collectors qualify their durable boundary before this snapshot reads events.
        $observations = new MessageObservations($this->entityManager, $messages);
        foreach ($campaigns as $campaign) {
            $id = 'campaign:'.$campaign['id'];
            yield $this->campaignReport($campaign, $groups[$id] ?? [], $observations, $coverages[$id]);
            unset($groups[$id]);
        }

        foreach ($groups as $id => $group) {
            $first = $group[0];
            if (str_starts_with($id, 'campaign:') && null !== $first->campaign?->id) {
                $campaign = $this->statistics->campaign($first->campaign->id);
                if (null !== $campaign) {
                    yield $this->campaignReport($campaign, $group, $observations, $coverages[$id]);
                }

                continue;
            }

            $label = $first->automation->name ?? '';
            if ('step' === $query->groupBy || 'occurrence' === $query->groupBy) {
                $label .= ' · '.($first->stepId ?? '');
            }

            if ('occurrence' === $query->groupBy) {
                $label .= ' · #'.$first->occurrenceId;
            }

            yield new Report(
                'pushword',
                $id,
                $label,
                $this->urls->generate('admin_newsletter_automation_edit', ['entityId' => $first->automation?->id]),
                $first->attemptedAt,
                $this->metrics($group, $query, $observations, $coverages[$id]->complete, $coverages[$id]->observedAt),
                'campaign' === $query->groupBy ? 'automation' : $query->groupBy,
                $this->methods($group, $observations),
                $this->scopes($group),
                mature: $this->mature($group, $query),
                ratesAvailable: 'sent' === $query->basis,
                rateDenominators: $this->deliveryRateBases($coverages[$id]->complete),
                observedAt: $coverages[$id]->observedAt,
                notice: $coverages[$id]->notice,
                noticeLevel: $coverages[$id]->noticeLevel,
            );
        }
    }

    public function campaign(int $id, ReportQuery $query): Report
    {
        $campaign = $this->statistics->campaign($id) ?? throw new InvalidArgumentException('Unknown newsletter campaign.');
        $messages = $this->entityManager->getRepository(DeliveryMessage::class)->findBy(['campaign' => $id]);

        $coverage = $this->deliveryCoverage($messages, $query);

        return $this->campaignReport($campaign, $messages, new MessageObservations($this->entityManager, $messages), $coverage);
    }

    /** @param list<int> $campaignIds
     * @return list<DeliveryMessage>
     */
    private function messages(ReportQuery $query, array $campaignIds): array
    {
        $builder = $this->entityManager->createQueryBuilder()->select('m', 'contact', 'campaign', 'automation')
            ->from(DeliveryMessage::class, 'm')->join('m.contact', 'contact')->leftJoin('m.campaign', 'campaign')->leftJoin('m.automation', 'automation')
            ->where('m.attemptedAt <= :asOf')->setParameter('asOf', $query->asOf)
            ->setParameter('from', $query->from)->setParameter('until', $query->until)
            ->orderBy('m.attemptedAt', 'ASC')->addOrderBy('m.id', 'ASC');
        if (null !== $query->audienceId) {
            $builder->andWhere('IDENTITY(contact.audience) = :audience')->setParameter('audience', $query->audienceId);
        }

        $period = '(m.attemptedAt >= :from AND m.attemptedAt < :until)';
        if ('events' === $query->basis) {
            foreach (['delivery' => [DeliveryEvent::class, 'occurredAt'], 'click' => [ClickEvent::class, 'clickedAt'], 'subscription' => [ContactEvent::class, 'occurredAt']] as $alias => [$class, $date]) {
                $period .= ' OR EXISTS (SELECT '.$alias.'.id FROM '.$class.' '.$alias.' WHERE '.$alias.'.message = m AND '.$alias.'.'.$date.' >= :from AND '.$alias.'.'.$date.' < :until)';
            }
        } elseif ('campaign' === $query->groupBy) {
            $period = '(m.campaign IS NULL AND '.$period.')';
            if ([] !== $campaignIds) {
                $period .= ' OR IDENTITY(m.campaign) IN (:campaigns)';
                $builder->setParameter('campaigns', $campaignIds);
            }
        }

        /** @var list<DeliveryMessage> */
        return $builder->andWhere('('.$period.')')->getQuery()->getResult();
    }

    private function groupId(DeliveryMessage $message, ReportQuery $query): string
    {
        if ('campaign' === $query->groupBy && null !== $message->campaign) {
            return 'campaign:'.$message->campaign->id;
        }

        $id = 'automation:'.$message->automation?->id;
        if (\in_array($query->groupBy, ['step', 'occurrence'], true)) {
            $id .= ':step:'.$message->stepId;
        }

        if ('occurrence' === $query->groupBy) {
            $id .= ':occurrence:'.$message->occurrenceId;
        }

        return $id;
    }

    /** @param CampaignStatistics $campaign
     * @param list<DeliveryMessage> $messages
     */
    private function campaignReport(array $campaign, array $messages, MessageObservations $observations, QualifyDeliveryCoverage $coverage): Report
    {
        $query = $coverage->query;
        if ('sent' === $query->basis && $campaign['sent'] !== \count(array_filter($messages, static fn (DeliveryMessage $message): bool => null !== $message->acceptedAt && $message->acceptedAt <= $query->asOf))) {
            $coverage->complete = false;
        }

        $metrics = $this->metrics($messages, $query, $observations, $coverage->complete, $coverage->observedAt);
        if ('sent' === $query->basis) {
            $metrics['accepted'] = new Metric($campaign['sent']);
            $metrics['transportFailed'] = new Metric($campaign['failed']);
            if ($campaign['sent'] !== $metrics['coverageKnownMessages']->value) {
                $metrics['trackedMessages'] = Metric::notMeasured();
            }

            if (null === $query->windowDays && null === $query->clickScope) {
                $metrics['clicks'] = $campaign['tracking'] || $campaign['clicks'] > 0 ? new Metric($campaign['clicks']) : Metric::notMeasured();
                $metrics['clickers'] = 'measured' === $metrics['clicks']->state ? new Metric($campaign['clickers']) : Metric::notMeasured();
                if (! $coverage->complete) {
                    $metrics['bounced'] = new Metric($campaign['bounced'], complete: false);
                }

                $metrics['unsubscribed'] = new Metric($campaign['unsubscribed']);
            } elseif ($campaign['sent'] !== $metrics['coverageKnownMessages']->value) {
                foreach (['clicks', 'clickers', 'bounced', 'unsubscribed'] as $name) {
                    $metrics[$name] = Metric::unavailable();
                }
            }
        }

        return new Report(
            'pushword',
            (string) $campaign['id'],
            $campaign['subject'],
            $this->urls->generate('admin_newsletter_campaign_detail', ['entityId' => $campaign['id']]),
            null !== $campaign['date'] ? new DateTimeImmutable($campaign['date']) : null,
            $metrics,
            qualificationMethod: $this->methods($messages, $observations),
            trackingScope: $this->scopes($messages),
            mature: $this->mature($messages, $query),
            ratesAvailable: 'sent' === $query->basis,
            rateDenominators: $this->deliveryRateBases($coverage->complete),
            observedAt: $coverage->observedAt,
            notice: $coverage->notice,
            noticeLevel: $coverage->noticeLevel,
            links: $this->campaignLinks($campaign['id'], $messages, $observations, $query),
        );
    }

    /** @param list<DeliveryMessage> $messages
     * @return array<string, Metric>
     */
    private function metrics(array $messages, ReportQuery $query, MessageObservations $observations, bool $deliveryComplete, ?DateTimeImmutable $deliveryAsOf): array
    {
        $observed = $this->observedMessages($messages, $observations, $deliveryAsOf);
        $trackingObserved = array_any($messages, static fn (DeliveryMessage $message): bool => $message->trackingAllowed && null !== $message->acceptedAt
            && (null === $query->clickScope || \in_array($query->clickScope, $message->trackingScopes, true)));
        $coverage = array_fill_keys(Report::METRICS, true);
        // Native delivery notifications are explicit lower bounds, not a complete receiving-server feed.
        $coverage['complaints'] = false;
        $metrics = new ObservationAggregator()->metrics($observed, $query, new ObservationCoverage($coverage));
        if (! $deliveryComplete && 0 === $metrics['delivered']->value) {
            $metrics['delivered'] = Metric::unavailable();
        }

        if (! $deliveryComplete) {
            foreach (['delivered', 'deferred', 'bounced'] as $name) {
                $metrics[$name] = new Metric($metrics[$name]->value, $metrics[$name]->state, false);
            }
        }

        if (! $trackingObserved) {
            foreach (['clicks', 'clickers', 'filteredClicks', 'filteredClickers', 'qualifiedClicks', 'automatedClicks', 'unclassifiedClicks', 'clicksWithoutKnownBots', 'clickersWithoutKnownBots'] as $name) {
                $metrics[$name] = Metric::notMeasured();
            }
        }

        return $metrics;
    }

    /** @param list<DeliveryMessage> $messages */
    private function deliveryCoverage(array $messages, ReportQuery $query): QualifyDeliveryCoverage
    {
        $coverage = new QualifyDeliveryCoverage($messages, $query);
        $this->events?->dispatch($coverage);

        return $coverage;
    }

    /** @return array<key-of<Report::RATES>, null> */
    private function deliveryRateBases(bool $complete): array
    {
        return $complete ? [] : array_fill_keys(['deliveryRate', 'clickRateDelivered', 'filteredClickRateDelivered', 'clickRateWithoutKnownBotsDelivered'], null);
    }

    /** @param list<DeliveryMessage> $messages
     * @return list<LinkReport>
     */
    private function campaignLinks(int $campaignId, array $messages, MessageObservations $observations, ReportQuery $query): array
    {
        $observed = $this->observedMessages($messages, $observations);
        foreach ($this->entityManager->getRepository(ClickEvent::class)->findBy(['campaign' => $campaignId, 'message' => null]) as $click) {
            $observed[] = new ObservedMessage(
                null,
                null === $click->contact->id ? null : (string) $click->contact->id,
                null,
                events: [new ObservedEvent('click', $click->clickedAt, $click->automated, $click->qualificationMethod, $click->scope, $click->linkId, $click->url)]
            );
        }

        return new ObservationAggregator()->links($observed, $query, new ObservationCoverage(['clicks' => true, 'clickers' => true]));
    }

    /** @param list<DeliveryMessage> $messages
     * @return list<ObservedMessage>
     */
    private function observedMessages(array $messages, MessageObservations $observations, ?DateTimeImmutable $deliveryAsOf = null): array
    {
        $observed = [];
        foreach ($messages as $message) {
            $events = [];
            foreach ($observations->deliveries[$message->id ?? 0] ?? [] as $event) {
                if (null !== $deliveryAsOf && $event->occurredAt > $deliveryAsOf) {
                    continue;
                }

                $events[] = new ObservedEvent(match ($event->kind) {
                    DeliveryEventKind::RemoteAccepted => 'delivered',
                    DeliveryEventKind::Deferred => 'deferred',
                    DeliveryEventKind::Failed => 'bounced',
                }, $event->occurredAt);
            }

            foreach ($observations->clicks[$message->id ?? 0] ?? [] as $click) {
                $events[] = new ObservedEvent('click', $click->clickedAt, $click->automated, $click->qualificationMethod, $click->scope, $click->linkId, $click->url);
            }

            foreach ($observations->unsubscribes[$message->id ?? 0] ?? [] as $event) {
                $events[] = new ObservedEvent('unsubscribed', $event->occurredAt);
            }

            $observed[] = new ObservedMessage(
                $message->reference,
                null === $message->contact->id ? null : (string) $message->contact->id,
                $message->attemptedAt,
                $message->acceptedAt,
                $message->trackingAllowed,
                null !== $message->transportError,
                $events,
                $message->trackingScopes
            );
        }

        return $observed;
    }

    /** @param list<DeliveryMessage> $messages */
    private function scopes(array $messages): string
    {
        $scopes = [];
        foreach ($messages as $message) {
            foreach ($message->trackingScopes as $scope) {
                $scopes[$scope] = true;
            }
        }

        return [] === $scopes || ['body'] === array_keys($scopes) ? 'body; navigation and footer excluded' : implode(', ', array_keys($scopes));
    }

    /** @param list<DeliveryMessage> $messages */
    private function methods(array $messages, MessageObservations $observations): string
    {
        $methods = [];
        foreach ($messages as $message) {
            foreach (($observations->clicks[$message->id ?? 0] ?? []) as $click) {
                $methods[$click->qualificationMethod] = true;
            }
        }

        return [] === $methods ? 'unclassified' : implode(', ', array_keys($methods));
    }

    /** @param list<DeliveryMessage> $messages */
    private function mature(array $messages, ReportQuery $query): bool
    {
        return null === $query->windowDays || ([] !== $messages && array_all($messages, static fn (DeliveryMessage $message): bool => ($message->acceptedAt ?? $message->attemptedAt)->modify('+'.$query->windowDays.' days') <= $query->asOf));
    }

    /** @return iterable<Report> Old drips have no send reference, consent snapshot or event window. */
    private function legacyAutomations(ReportQuery $query): iterable
    {
        if ('events' === $query->basis) {
            return;
        }

        $builder = $this->entityManager->getRepository(AutomationDelivery::class)->createQueryBuilder('d')
            ->join('d.contact', 'c')->where('d.message IS NULL AND d.attemptedAt >= :from AND d.attemptedAt < :until')
            ->setParameter('from', $query->from)->setParameter('until', $query->until);
        if (null !== $query->audienceId) {
            $builder->andWhere('IDENTITY(c.audience) = :audience')->setParameter('audience', $query->audienceId);
        }

        /** @var list<AutomationDelivery> $deliveries */
        $deliveries = $builder->getQuery()->getResult();
        $groups = [];
        foreach ($deliveries as $delivery) {
            $id = 'automation:'.$delivery->automation->id;
            if (\in_array($query->groupBy, ['step', 'occurrence'], true)) {
                $id .= ':step:legacy-position-'.$delivery->position;
            }

            if ('occurrence' === $query->groupBy) {
                $id .= ':occurrence:'.$delivery->subjectId;
            }

            $groups[$id][] = $delivery;
        }

        foreach ($groups as $id => $group) {
            $metrics = array_fill_keys(Report::METRICS, Metric::unavailable());
            $accepted = 0;
            $failed = 0;
            foreach ($group as $delivery) {
                if (\in_array($delivery->state->value, ['sent', 'bounced'], true)) {
                    ++$accepted;
                } elseif ('failed' === $delivery->state->value) {
                    ++$failed;
                }
            }

            $metrics['accepted'] = new Metric($accepted);
            $metrics['transportFailed'] = new Metric($failed);
            $metrics['trackedMessages'] = Metric::notMeasured();
            yield new Report('pushword', $id, $group[0]->automation->name, $this->urls->generate('admin_newsletter_automation_edit', ['entityId' => $group[0]->automation->id]), $group[0]->attemptedAt, $metrics, 'campaign' === $query->groupBy ? 'automation' : $query->groupBy, mature: null === $query->windowDays, ratesAvailable: false);
        }
    }
}
