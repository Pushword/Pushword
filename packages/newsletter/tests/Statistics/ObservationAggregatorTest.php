<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Statistics\LinkReport;
use Pushword\Newsletter\Statistics\Metric;
use Pushword\Newsletter\Statistics\ObservationAggregator;
use Pushword\Newsletter\Statistics\ObservationCoverage;
use Pushword\Newsletter\Statistics\ObservedEvent;
use Pushword\Newsletter\Statistics\ObservedMessage;
use Pushword\Newsletter\Statistics\Report;
use Pushword\Newsletter\Statistics\ReportQuery;

final class ObservationAggregatorTest extends TestCase
{
    public function testAnUnverifiedCallbackStreamNeverInventsZeroOrTotalClicks(): void
    {
        $messages = [$this->message('a', [new ObservedEvent('click', $this->date('+1 day'))])];
        $aggregator = new ObservationAggregator();
        $unknown = $aggregator->metrics($messages, $this->query(), new ObservationCoverage());
        foreach ($unknown as $metric) {
            self::assertSame('unavailable', $metric->state);
            self::assertNull($metric->value);
        }

        $coverage = new ObservationCoverage(['clickers' => true, 'clicks' => false, 'complaints' => true]);
        $known = $aggregator->metrics($messages, $this->query(), $coverage);
        self::assertSame(1, $known['clickers']->value);
        self::assertNull($known['clicks']->value);
        self::assertSame(0, $known['complaints']->value);
    }

    public function testDatedEventsUseChronologicalDeliveryStateAndIndependentRecipientIdentity(): void
    {
        $events = [
            new ObservedEvent('delivered', $this->date('+3 days')),
            new ObservedEvent('deferred', $this->date('+1 day')),
            new ObservedEvent('click', $this->date('+2 days')),
            new ObservedEvent('click', $this->date('+3 days')),
            new ObservedEvent('unsubscribed', $this->date('+4 days')),
        ];
        $messages = [$this->message('a', $events), new ObservedMessage('another-attempt', 'a', $this->date(), $this->date(), true, events: [new ObservedEvent('click', $this->date('+4 days'))])];
        $metrics = new ObservationAggregator()->metrics($messages, $this->query(), $this->complete());
        self::assertSame(2, $metrics['accepted']->value);
        self::assertSame(1, $metrics['delivered']->value);
        self::assertSame(0, $metrics['deferred']->value);
        self::assertSame(3, $metrics['clicks']->value);
        self::assertSame(1, $metrics['clickers']->value);
        self::assertSame(1, $metrics['unsubscribed']->value);
    }

    public function testRelativeWindowsAreExclusiveAndRejectFutureOrPreSendEvents(): void
    {
        $events = array_map(fn (string $date): ObservedEvent => new ObservedEvent('click', $this->date($date)), ['-1 second', '+1 day', '+7 days', '+31 days']);
        $messages = [$this->message('a', $events)];
        $aggregator = new ObservationAggregator();
        self::assertSame(1, $aggregator->metrics($messages, $this->query(window: 7), $this->complete())['clicks']->value);
        self::assertSame(2, $aggregator->metrics($messages, $this->query(window: 30), $this->complete())['clicks']->value);
        self::assertSame(2, $aggregator->metrics($messages, $this->query(), $this->complete())['clicks']->value);
        self::assertFalse($aggregator->mature($messages, $this->query(window: 30, asOf: '+20 days')));
        self::assertTrue($aggregator->mature($messages, $this->query(window: 30)));
    }

    public function testAnEventPeriodKeepsItsOwnDateBoundsWithoutChangingTheSelectedSendCohort(): void
    {
        $query = new ReportQuery($this->date('+2 days'), $this->date('+4 days'), basis: 'events', asOf: $this->date('+20 days'));
        $messages = [$this->message('a', [new ObservedEvent('click', $this->date('+2 days')), new ObservedEvent('click', $this->date('+4 days'))])];
        $metrics = new ObservationAggregator()->metrics($messages, $query, $this->complete());
        self::assertSame(0, $metrics['accepted']->value);
        self::assertSame(1, $metrics['clicks']->value);
    }

    public function testFailedAttemptsAndFutureSendsDoNotInflateAcceptedOrTrackedCounts(): void
    {
        $messages = [
            new ObservedMessage('failed', 'a', $this->date(), trackingAllowed: true, transportFailed: true),
            new ObservedMessage('future', 'b', $this->date('+31 days'), $this->date('+31 days'), true, trackingScopes: ['body']),
        ];
        $metrics = new ObservationAggregator()->metrics($messages, $this->query(), $this->complete());

        self::assertSame(0, $metrics['accepted']->value);
        self::assertSame(1, $metrics['transportFailed']->value);
        self::assertSame(0, $metrics['trackedMessages']->value);
        self::assertSame(0, $metrics['coverageKnownMessages']->value);
    }

    public function testSystemLinksNeverCountAsCampaignClicks(): void
    {
        $messages = [$this->message('a', [new ObservedEvent('click', $this->date('+1 day'), scope: 'system', url: 'https://example.org/unsubscribe')])];
        $aggregator = new ObservationAggregator();
        $metrics = $aggregator->metrics($messages, $this->query(), $this->complete());

        self::assertSame(0, $metrics['clicks']->value);
        self::assertSame(0, $metrics['clickers']->value);
        self::assertSame([], $aggregator->links($messages, $this->query(), $this->complete()));
    }

    public function testUnknownRecipientsAndSendDatesDoNotAcquireSyntheticReferences(): void
    {
        $message = new ObservedMessage(null, null, null, events: [new ObservedEvent('click', $this->date('+1 day'))]);
        $aggregator = new ObservationAggregator();
        $metrics = $aggregator->metrics([$message], $this->query(), $this->complete());
        self::assertSame(1, $metrics['clicks']->value);
        self::assertNull($metrics['clickers']->value);
        self::assertNull($metrics['accepted']->value);
        self::assertNull($metrics['trackedMessages']->value);
        self::assertNull($aggregator->metrics([$message], $this->query(window: 7), $this->complete())['clicks']->value);
        self::assertFalse($aggregator->mature([$message], $this->query(window: 7)));
    }

    public function testKnownBotsUnknownAndExplicitlyQualifiedClicksRemainDifferent(): void
    {
        $messages = [
            $this->message('bot', [new ObservedEvent('click', $this->date('+1 day'), true, 'known-user-agent/v1')]),
            $this->message('unknown', [new ObservedEvent('click', $this->date('+1 day'), null, 'known-user-agent/v1')]),
            $this->message('qualified', [new ObservedEvent('click', $this->date('+1 day'), false, 'application-proof/v1')]),
        ];
        $metrics = new ObservationAggregator()->metrics($messages, $this->query(), $this->complete());
        foreach (['clicks' => 3, 'qualifiedClicks' => 2, 'automatedClicks' => 1, 'unclassifiedClicks' => 1, 'filteredClicks' => 1, 'filteredClickers' => 1, 'clicksWithoutKnownBots' => 2, 'clickersWithoutKnownBots' => 2] as $name => $expected) {
            self::assertSame($expected, $metrics[$name]->value, $name);
        }

        $unexamined = new ObservationAggregator()->metrics([$this->message('a', [new ObservedEvent('click', $this->date('+1 day'))])], $this->query(), $this->complete());
        self::assertNull($unexamined['filteredClicks']->value);
        self::assertNull($unexamined['clicksWithoutKnownBots']->value);
    }

    public function testScopeCoverageUsesTheActualSendInstrumentationAndDoesNotAffectDelivery(): void
    {
        $messages = [
            $this->message('old', [], ['body']),
            $this->message('new', [new ObservedEvent('click', $this->date('+1 day'), scope: 'footer')], ['body', 'footer']),
        ];
        $metrics = new ObservationAggregator()->metrics($messages, $this->query(scope: 'footer'), $this->complete());
        self::assertSame(2, $metrics['accepted']->value);
        self::assertSame(2, $metrics['coverageKnownMessages']->value);
        self::assertSame(1, $metrics['trackedMessages']->value);
        self::assertSame(1, $metrics['clickers']->value);

        $unknown = [$this->message('external', [new ObservedEvent('click', $this->date('+1 day'))], null)];
        $metrics = new ObservationAggregator()->metrics($unknown, $this->query(scope: 'body'), $this->complete());
        self::assertSame(1, $metrics['accepted']->value);
        self::assertNull($metrics['trackedMessages']->value);
        self::assertNull($metrics['clicks']->value);
    }

    public function testCtaAndDestinationVersionsRemainSeparateAndCountsHaveIndependentStates(): void
    {
        $messages = [$this->message('a', [
            new ObservedEvent('click', $this->date('+1 day'), scope: 'body', linkId: 'top', url: 'https://example.org/one'),
            new ObservedEvent('click', $this->date('+2 days'), scope: 'body', linkId: 'bottom', url: 'https://example.org/one'),
            new ObservedEvent('click', $this->date('+3 days'), scope: 'body', linkId: 'top', url: 'https://example.org/two'),
            new ObservedEvent('click', $this->date('+4 days'), scope: 'system', linkId: 'leave', url: 'https://example.org/unsubscribe'),
        ])];
        $links = new ObservationAggregator()->links($messages, $this->query(), new ObservationCoverage(['clicks' => false, 'clickers' => true]));
        self::assertCount(3, $links);
        foreach ($links as $link) {
            self::assertSame(1, $link->clickers->value);
            self::assertSame('unavailable', $link->clicks->state);
            self::assertNotNull($link->safeUrl());
        }

        self::assertNull(new LinkReport('javascript:alert(1)', null, 'unknown', new Metric(1), new Metric(1))->safeUrl());
    }

    public function testKnownAttemptReferencesCannotBeDoubleCounted(): void
    {
        $message = $this->message('a', []);
        $this->expectException(InvalidArgumentException::class);
        new ObservationAggregator()->metrics([$message, $message], $this->query(), $this->complete());
    }

    public function testConflictingDeliveryOutcomesAtTheSameTimestampDoNotUseArrivalOrder(): void
    {
        $events = [new ObservedEvent('delivered', $this->date('+1 day')), new ObservedEvent('deferred', $this->date('+1 day'))];
        $aggregator = new ObservationAggregator();
        foreach ([$events, array_reverse($events)] as $selection) {
            $metrics = $aggregator->metrics([$this->message('a', $selection)], $this->query(), $this->complete());
            self::assertSame(1, $metrics['accepted']->value);
            self::assertNull($metrics['delivered']->value);
            self::assertNull($metrics['deferred']->value);
        }

        $events[] = new ObservedEvent('delivered', $this->date('+2 days'));
        self::assertSame(1, $aggregator->metrics([$this->message('a', $events)], $this->query(), $this->complete())['delivered']->value);
    }

    /** @param list<ObservedEvent> $events
     * @param list<string>|null $scopes
     */
    private function message(string $recipient, array $events, ?array $scopes = ['body']): ObservedMessage
    {
        return new ObservedMessage('message-'.$recipient, $recipient, $this->date(), $this->date(), true, events: $events, trackingScopes: $scopes);
    }

    private function query(?int $window = null, ?string $scope = null, string $asOf = '+30 days'): ReportQuery
    {
        return new ReportQuery($this->date(), $this->date('+1 day'), windowDays: $window, asOf: $this->date($asOf), clickScope: $scope);
    }

    private function date(string $offset = '+0 seconds'): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01 00:00:00 UTC')->modify($offset);
    }

    private function complete(): ObservationCoverage
    {
        return new ObservationCoverage(array_fill_keys(Report::METRICS, true));
    }
}
