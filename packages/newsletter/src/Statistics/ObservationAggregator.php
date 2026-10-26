<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;

/** Shared counting rules for native and external messages; no Doctrine or source HTTP dependency. */
final readonly class ObservationAggregator
{
    /** @param iterable<ObservedMessage> $messages
     * @return array<string, Metric>
     */
    public function metrics(iterable $messages, ReportQuery $query, ObservationCoverage $coverage): array
    {
        $counts = array_fill_keys(Report::METRICS, 0);
        $recipients = ['clickers' => [], 'filteredClickers' => [], 'clickersWithoutKnownBots' => []];
        $unknownRecipients = [];
        $unknownOrigin = false;
        $unknownTracking = false;
        $unexaminedClicks = false;
        $unknownScope = false;
        $ambiguousDeliveries = false;
        $references = [];
        foreach ($messages as $message) {
            if (null !== $message->reference) {
                if (isset($references[$message->reference])) {
                    throw new InvalidArgumentException('A report cohort must contain each known message reference once.');
                }

                $references[$message->reference] = true;
            }

            $sentAt = $message->acceptedAt ?? $message->attemptedAt;
            if (null === $sentAt) {
                $unknownOrigin = true;
            }

            if (null !== $sentAt && $sentAt > $query->asOf) {
                continue;
            }

            if (null !== $sentAt && ('sent' === $query->basis || $query->contains($sentAt))) {
                if (null !== $message->acceptedAt) {
                    ++$counts['accepted'];
                    $instrumented = true;
                    if (null !== $query->clickScope) {
                        $instrumented = null === $message->trackingScopes ? null : \in_array($query->clickScope, $message->trackingScopes, true);
                    }

                    if (null !== $message->trackingAllowed && null !== $instrumented) {
                        ++$counts['coverageKnownMessages'];
                    } else {
                        $unknownTracking = true;
                    }

                    if (true === $message->trackingAllowed && true === $instrumented) {
                        ++$counts['trackedMessages'];
                    }
                }

                if ($message->transportFailed) {
                    ++$counts['transportFailed'];
                }
            }

            $events = $message->events;
            // Timestamp order matters when callbacks arrive late or are replayed.
            usort($events, static fn (ObservedEvent $a, ObservedEvent $b): int => $a->occurredAt <=> $b->occurredAt);
            $delivery = null;
            $deliveryAt = null;
            $ambiguousDelivery = false;
            foreach ($events as $event) {
                if (! $this->observes($query, $sentAt, $event->occurredAt)) {
                    continue;
                }

                if (\in_array($event->kind, ['delivered', 'deferred', 'bounced'], true)) {
                    $ambiguousDelivery = null !== $deliveryAt && 0 === ($event->occurredAt <=> $deliveryAt) && ($ambiguousDelivery || $delivery !== $event->kind);
                    $delivery = $event->kind;
                    $deliveryAt = $event->occurredAt;

                    continue;
                }

                if ('click' !== $event->kind) {
                    ++$counts['complaint' === $event->kind ? 'complaints' : 'unsubscribed'];

                    continue;
                }

                if ('system' === $event->scope) {
                    continue;
                }

                if (null !== $query->clickScope && $query->clickScope !== $event->scope) {
                    $unknownScope = $unknownScope || 'unknown' === $event->scope;

                    continue;
                }

                ++$counts['clicks'];
                $keys = ['clickers'];
                if (null !== $event->automated) {
                    ++$counts['qualifiedClicks'];
                }

                if (true === $event->automated) {
                    ++$counts['automatedClicks'];
                } else {
                    ++$counts['clicksWithoutKnownBots'];
                    $keys[] = 'clickersWithoutKnownBots';
                }

                if (false === $event->automated) {
                    ++$counts['filteredClicks'];
                    $keys[] = 'filteredClickers';
                } elseif (null === $event->automated) {
                    ++$counts['unclassifiedClicks'];
                }

                $unexaminedClicks = $unexaminedClicks || 'unclassified' === $event->qualificationMethod;
                foreach ($keys as $key) {
                    if (null === $message->recipientKey) {
                        $unknownRecipients[$key] = true;
                    } else {
                        $recipients[$key][$message->recipientKey] = true;
                    }
                }
            }

            if ($ambiguousDelivery) {
                $ambiguousDeliveries = true;
            } elseif (null !== $delivery) {
                ++$counts[$delivery];
            }
        }

        foreach ($recipients as $name => $keys) {
            $counts[$name] = \count($keys);
        }

        $metrics = array_fill_keys(Report::METRICS, Metric::unavailable());
        foreach ($counts as $name => $count) {
            $needsOrigin = null !== $query->windowDays || \in_array($name, ['accepted', 'transportFailed', 'trackedMessages', 'coverageKnownMessages'], true);
            $metrics[$name] = (! $unknownOrigin || ! $needsOrigin) && $coverage->complete($name) && ! isset($unknownRecipients[$name])
                ? new Metric($count) : Metric::unavailable();
        }

        if ($unknownTracking) {
            $metrics['trackedMessages'] = Metric::unavailable();
        }

        if (0 === $counts['qualifiedClicks']) {
            foreach (['filteredClicks', 'filteredClickers'] as $name) {
                if ('measured' === $metrics[$name]->state) {
                    $metrics[$name] = Metric::notMeasured();
                }
            }
        }

        if ($unexaminedClicks) {
            foreach (['clicksWithoutKnownBots', 'clickersWithoutKnownBots'] as $name) {
                $metrics[$name] = Metric::unavailable();
            }
        }

        if ($unknownScope) {
            foreach (['clicks', 'clickers', 'filteredClicks', 'filteredClickers', 'qualifiedClicks', 'automatedClicks', 'unclassifiedClicks', 'clicksWithoutKnownBots', 'clickersWithoutKnownBots'] as $name) {
                $metrics[$name] = Metric::unavailable();
            }
        }

        if ($ambiguousDeliveries) {
            foreach (['delivered', 'deferred', 'bounced'] as $name) {
                $metrics[$name] = Metric::unavailable();
            }
        }

        return $metrics;
    }

    /** @param iterable<ObservedMessage> $messages
     * @return list<LinkReport>
     */
    public function links(iterable $messages, ReportQuery $query, ObservationCoverage $coverage): array
    {
        $messages = \is_array($messages) ? $messages : iterator_to_array($messages, false);
        $metrics = $this->metrics($messages, $query, $coverage);
        $groups = [];
        foreach ($messages as $message) {
            $sentAt = $message->acceptedAt ?? $message->attemptedAt;
            if (null !== $sentAt && $sentAt > $query->asOf) {
                continue;
            }

            foreach ($message->events as $event) {
                if ('click' !== $event->kind || 'system' === $event->scope || null === $event->url
                    || ! $this->observes($query, $sentAt, $event->occurredAt)
                    || (null !== $query->clickScope && $query->clickScope !== $event->scope)) {
                    continue;
                }

                $key = json_encode([$event->scope, $event->linkId, $event->url], \JSON_THROW_ON_ERROR);
                $groups[$key] ??= ['event' => $event, 'clicks' => 0, 'recipients' => [], 'unknownRecipient' => false];
                ++$groups[$key]['clicks'];
                if (null === $message->recipientKey) {
                    $groups[$key]['unknownRecipient'] = true;
                } else {
                    $groups[$key]['recipients'][$message->recipientKey] = true;
                }
            }
        }

        $links = [];
        foreach ($groups as $group) {
            $clicks = 'measured' === $metrics['clicks']->state ? new Metric($group['clicks']) : new Metric(null, $metrics['clicks']->state);
            $clickers = 'measured' === $metrics['clickers']->state && ! $group['unknownRecipient']
                ? new Metric(\count($group['recipients'])) : Metric::unavailable();
            $links[] = new LinkReport($group['event']->url ?? '', $group['event']->linkId, $group['event']->scope, $clicks, $clickers);
        }

        usort($links, static fn (LinkReport $a, LinkReport $b): int => $b->clickers->value <=> $a->clickers->value ?: $b->clicks->value <=> $a->clicks->value ?: strcmp($a->url, $b->url));

        return $links;
    }

    private function observes(ReportQuery $query, ?DateTimeImmutable $sentAt, DateTimeImmutable $eventAt): bool
    {
        if (null !== $sentAt) {
            return $query->observes($sentAt, $eventAt);
        }

        // An undated legacy send can still have real dated events, but no relative observation window.
        return null === $query->windowDays && $eventAt <= $query->asOf && ('sent' === $query->basis || $query->contains($eventAt));
    }

    /** @param list<ObservedMessage> $messages */
    public function mature(array $messages, ReportQuery $query): bool
    {
        return null === $query->windowDays || ([] !== $messages && array_all($messages, static function (ObservedMessage $message) use ($query): bool {
            $date = $message->acceptedAt ?? $message->attemptedAt;

            return null !== $date && $date->modify('+'.$query->windowDays.' days') <= $query->asOf;
        }));
    }
}
