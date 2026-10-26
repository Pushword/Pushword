<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Statistics\Metric;
use Pushword\Newsletter\Statistics\Report;
use Pushword\Newsletter\Statistics\ReportQuery;
use Pushword\Newsletter\Statistics\StatisticsDashboard;
use Pushword\Newsletter\Statistics\StatisticsProvider;

final class StatisticsContractTest extends TestCase
{
    public function testZeroMissingAndUnavailableRemainDistinctIncludingInTotals(): void
    {
        self::assertSame(0, new Metric(0)->value);
        self::assertSame('notMeasured', Metric::notMeasured()->state);
        self::assertSame('unavailable', Metric::unavailable()->state);
        self::assertSame('unavailable', Metric::sum([new Metric(20), Metric::unavailable()])->state);
        self::assertNull(Metric::sum([new Metric(20), Metric::notMeasured()])->value);
        $this->expectException(InvalidArgumentException::class);
        new Metric(null);
    }

    public function testIncompleteCountsKeepTheirObservedFactsButNeverBecomeAnExhaustiveRate(): void
    {
        $partial = new Metric(1, complete: false);
        $sum = Metric::sum([$partial, new Metric(2)]);
        self::assertSame(3, $sum->value);
        self::assertFalse($sum->complete);
        $report = new Report('pushword', 'partial', 'Partial', '', null, ['accepted' => new Metric(10), 'delivered' => $sum, 'clickers' => new Metric(2)]);
        self::assertNull($report->rate('deliveryRate')['value']);
        self::assertSame('unavailable', $report->rate('deliveryRate')['state']);
        self::assertNull($report->rate('clickRateDelivered')['value']);
        self::assertFalse(new StatisticsDashboard([])->totals([$report])->metric('delivered')->complete);
        $zeroLowerBound = new Report('external', 'zero', 'Partial zero', '', null, ['delivered' => new Metric(0, complete: false), 'clickers' => new Metric(1)]);
        self::assertSame('unavailable', $zeroLowerBound->rate('clickRateDelivered')['state']);
    }

    public function testSourcesKeepTheirIdentityManagementLinkAndWeightedFractions(): void
    {
        $local = new Report('pushword', '42', 'Local', '/admin/local/42', null, ['accepted' => new Metric(2), 'delivered' => new Metric(2), 'clickers' => new Metric(1)], rateDenominators: ['clickRate' => 'delivered']);
        $external = new Report('brevo', '42', 'External', 'https://app.brevo.com/campaign/42', null, ['accepted' => new Metric(8), 'delivered' => new Metric(8), 'clickers' => new Metric(2)], rateDenominators: ['clickRate' => 'delivered']);
        $provider = new readonly class([$local, $external]) implements StatisticsProvider {
            /** @param list<Report> $reports */
            public function __construct(private array $reports)
            {
            }

            public function reports(ReportQuery $query): iterable
            {
                return $this->reports;
            }
        };
        $dashboard = new StatisticsDashboard([$provider]);
        $reports = $dashboard->reports(new ReportQuery(new DateTimeImmutable('yesterday'), new DateTimeImmutable('tomorrow')));
        self::assertCount(2, $reports);
        self::assertSame('https://app.brevo.com/campaign/42', $reports[0]->managementUrl);
        $totals = $dashboard->totals($reports);
        $rate = $totals->rate('clickRate');
        self::assertSame(30.0, $rate['value']);
        self::assertSame(3, $rate['numerator']->value);
        self::assertSame(10, $rate['denominator']->value);
        self::assertSame('delivered', $rate['denominatorMetric']);
        self::assertSame('unavailable', $totals->metric('trackedMessages')->state);
        self::assertSame('unavailable', $totals->rate('coverage')['state']);
        self::assertSame(100.0, $totals->rate('deliveryRate')['value']);
        $this->expectException(LogicException::class);
        new StatisticsDashboard([$provider, $provider])->reports(new ReportQuery(new DateTimeImmutable('yesterday'), new DateTimeImmutable('tomorrow')));
    }

    public function testDefaultRateBasesAndObservationMetadataRemainBackwardCompatible(): void
    {
        $report = new Report('pushword', '42', 'Local', '/admin/local/42', null, [
            'accepted' => new Metric(10),
            'delivered' => new Metric(8),
            'trackedMessages' => new Metric(5),
            'clickers' => new Metric(1),
            'filteredClickers' => new Metric(2),
            'bounced' => new Metric(1),
            'unsubscribed' => new Metric(0),
        ]);

        self::assertSame([], $report->rateDenominators);
        self::assertNull($report->observedAt);
        self::assertNull($report->notice);
        self::assertSame('info', $report->noticeLevel);
        foreach ([
            'clickRate' => [20.0, 'trackedMessages'],
            'filteredClickRate' => [40.0, 'trackedMessages'],
            'coverage' => [50.0, 'accepted'],
            'deliveryRate' => [80.0, 'accepted'],
            'bounceRate' => [10.0, 'accepted'],
            'unsubscribeRate' => [0.0, 'accepted'],
            'clickRateDelivered' => [12.5, 'delivered'],
            'filteredClickRateDelivered' => [25.0, 'delivered'],
        ] as $name => [$value, $denominator]) {
            foreach ([$report, new StatisticsDashboard([])->totals([$report])] as $result) {
                $rate = $result->rate($name);
                self::assertSame($value, $rate['value'], $name);
                self::assertSame('measured', $rate['state'], $name);
                self::assertSame($denominator, $rate['denominatorMetric'], $name);
            }
        }
    }

    public function testDeliveredBaseDoesNotInventTrackedMessages(): void
    {
        $report = new Report('external', '42', 'External', '/external/42', null, [
            'accepted' => new Metric(12),
            'delivered' => new Metric(10),
            'clickers' => new Metric(2),
        ], rateDenominators: ['clickRate' => 'delivered']);

        $rate = $report->rate('clickRate');
        self::assertSame(20.0, $rate['value']);
        self::assertSame('measured', $rate['state']);
        self::assertSame('clickers', $rate['numeratorMetric']);
        self::assertSame('delivered', $rate['denominatorMetric']);
        self::assertSame(2, $rate['numerator']->value);
        self::assertSame(10, $rate['denominator']->value);
        self::assertSame('unavailable', $report->metric('trackedMessages')->state);
        self::assertSame('unavailable', $report->rate('coverage')['state']);
        self::assertSame(83.33, $report->rate('deliveryRate')['value']);
    }

    public function testEmptyTotalsKeepDefaultBasesAndDoNotInventMeasuredRates(): void
    {
        $totals = new StatisticsDashboard([])->totals([]);

        self::assertSame([], $totals->rateDenominators);
        self::assertSame(0, $totals->metric('accepted')->value);
        self::assertSame(0, $totals->metric('trackedMessages')->value);
        foreach (array_keys(Report::RATES) as $name) {
            $rate = $totals->rate($name);
            self::assertNull($rate['value'], $name);
            self::assertSame('notMeasured', $rate['state'], $name);
            self::assertSame(0, $rate['denominator']->value, $name);
        }
    }

    public function testTotalsDoNotInventASharedObservationOrMergeSourceNotices(): void
    {
        $local = new Report('pushword', '42', 'Local', '/admin/local/42', null, [
            'accepted' => new Metric(10),
        ], observedAt: new DateTimeImmutable('2026-10-25T09:00:00Z'), notice: 'Current snapshot');
        $external = new Report('external', '42', 'External', '/external/42', null, [
            'accepted' => new Metric(20),
        ], observedAt: new DateTimeImmutable('2026-10-24T09:00:00Z'), notice: 'Retained snapshot', noticeLevel: 'warning');

        $totals = new StatisticsDashboard([])->totals([$local, $external]);
        self::assertSame(30, $totals->metric('accepted')->value);
        self::assertNull($totals->observedAt);
        self::assertNull($totals->notice);
        self::assertSame('info', $totals->noticeLevel);
    }

    public function testNullBaseDisablesOnlyTheSelectedRate(): void
    {
        $report = new Report('pushword', '42', 'Local', '/admin/local/42', null, [
            'accepted' => new Metric(10),
            'delivered' => new Metric(8),
            'trackedMessages' => new Metric(10),
            'clickers' => new Metric(2),
            'filteredClickers' => new Metric(1),
            'bounced' => new Metric(1),
            'unsubscribed' => new Metric(0),
        ], rateDenominators: ['clickRate' => null]);

        foreach ([$report, new StatisticsDashboard([])->totals([$report])] as $result) {
            $rate = $result->rate('clickRate');
            self::assertNull($rate['value']);
            self::assertNull($rate['denominatorMetric']);
            self::assertSame('unavailable', $rate['state']);
            self::assertSame(2, $rate['numerator']->value);
            self::assertSame(10, $result->metric('trackedMessages')->value);
            self::assertSame(10.0, $result->rate('filteredClickRate')['value']);
            self::assertSame(100.0, $result->rate('coverage')['value']);
            self::assertSame(80.0, $result->rate('deliveryRate')['value']);
            self::assertSame(10.0, $result->rate('bounceRate')['value']);
            self::assertSame(0.0, $result->rate('unsubscribeRate')['value']);
        }
    }

    public function testMixedBasesDisableOnlyTheirRateWhileKeepingOtherTotals(): void
    {
        $local = new Report('pushword', '42', 'Local', '/admin/local/42', null, [
            'accepted' => new Metric(10),
            'delivered' => new Metric(9),
            'trackedMessages' => new Metric(8),
            'clickers' => new Metric(2),
            'filteredClickers' => new Metric(1),
            'bounced' => new Metric(1),
            'unsubscribed' => new Metric(0),
        ]);
        $external = new Report('external', '42', 'External', '/external/42', null, [
            'accepted' => new Metric(20),
            'delivered' => new Metric(16),
            'trackedMessages' => new Metric(12),
            'clickers' => new Metric(4),
            'filteredClickers' => new Metric(3),
            'bounced' => new Metric(2),
            'unsubscribed' => new Metric(1),
        ], rateDenominators: ['clickRate' => 'delivered']);

        self::assertSame(25.0, $local->rate('clickRate')['value']);
        self::assertSame(25.0, $external->rate('clickRate')['value']);
        $totals = new StatisticsDashboard([])->totals([$local, $external]);
        $rate = $totals->rate('clickRate');
        self::assertNull($rate['value']);
        self::assertNull($rate['denominatorMetric']);
        self::assertSame('unavailable', $rate['state']);
        self::assertSame(6, $rate['numerator']->value);
        self::assertSame(30, $totals->metric('accepted')->value);
        self::assertSame(25, $totals->metric('delivered')->value);
        self::assertSame(20, $totals->metric('trackedMessages')->value);
        self::assertSame(20.0, $totals->rate('filteredClickRate')['value']);
        self::assertSame(66.67, $totals->rate('coverage')['value']);
        self::assertSame(83.33, $totals->rate('deliveryRate')['value']);
        self::assertSame(10.0, $totals->rate('bounceRate')['value']);
        self::assertSame(3.33, $totals->rate('unsubscribeRate')['value']);
    }

    /** @return iterable<string, array{Metric, Metric, bool, string, float|null}> */
    public static function rateStates(): iterable
    {
        yield 'measured zero' => [new Metric(0), new Metric(10), true, 'measured', 0.0];
        yield 'zero denominator' => [new Metric(2), new Metric(0), true, 'notMeasured', null];
        yield 'unavailable numerator' => [Metric::unavailable(), new Metric(10), true, 'unavailable', null];
        yield 'unavailable denominator' => [new Metric(2), Metric::unavailable(), true, 'unavailable', null];
        yield 'not measured denominator' => [new Metric(2), Metric::notMeasured(), true, 'notMeasured', null];
        yield 'rates disabled' => [new Metric(2), new Metric(10), false, 'notMeasured', null];
        yield 'rates disabled with unavailable metrics' => [Metric::unavailable(), Metric::unavailable(), false, 'notMeasured', null];
    }

    #[DataProvider('rateStates')]
    public function testOverriddenRatesPreserveMeasurementStates(Metric $numerator, Metric $denominator, bool $ratesAvailable, string $state, ?float $value): void
    {
        $report = new Report('external', '42', 'External', '/external/42', null, [
            'clickers' => $numerator,
            'delivered' => $denominator,
        ], ratesAvailable: $ratesAvailable, rateDenominators: ['clickRate' => 'delivered']);

        foreach ([$report, new StatisticsDashboard([])->totals([$report])] as $result) {
            $rate = $result->rate('clickRate');
            self::assertSame($value, $rate['value']);
            self::assertSame($state, $rate['state']);
            self::assertSame($numerator->state, $rate['numerator']->state);
            self::assertSame($denominator->state, $rate['denominator']->state);
            self::assertSame('delivered', $rate['denominatorMetric']);
        }
    }

    public function testWindowsHaveExclusiveUpperBoundariesAndEventPeriodsAreIndependentOfSendCohorts(): void
    {
        $sent = new DateTimeImmutable('2026-01-01');
        $query = new ReportQuery(new DateTimeImmutable('2026-01-04'), new DateTimeImmutable('2026-01-10'), basis: 'events', windowDays: 7, asOf: new DateTimeImmutable('2026-02-01'));
        self::assertFalse($query->contains($sent));
        self::assertTrue($query->observes($sent, new DateTimeImmutable('2026-01-07 23:59:59')));
        self::assertFalse($query->observes($sent, new DateTimeImmutable('2026-01-08')));
        self::assertFalse($query->observes($sent, new DateTimeImmutable('2026-01-03')));
        self::assertFalse($query->observes($sent, new DateTimeImmutable('2025-12-31')));
        self::assertNull(new Report('local', 'zero', '', '', null, ['clickers' => new Metric(0), 'trackedMessages' => new Metric(0)])->rate('clickRate')['value']);
    }
}
