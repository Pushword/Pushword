<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
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

    public function testSourcesKeepTheirIdentityManagementLinkAndWeightedFractions(): void
    {
        $local = new Report('pushword', '42', 'Local', '/admin/local/42', null, ['accepted' => new Metric(2), 'trackedMessages' => new Metric(2), 'clickers' => new Metric(1)]);
        $external = new Report('brevo', '42', 'External', 'https://app.brevo.com/campaign/42', null, ['accepted' => new Metric(8), 'trackedMessages' => new Metric(8), 'clickers' => new Metric(2)]);
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
        $rate = $dashboard->totals($reports)->rate('clickRate');
        self::assertSame(30.0, $rate['value']);
        self::assertSame(3, $rate['numerator']->value);
        self::assertSame(10, $rate['denominator']->value);
        self::assertSame(100.0, $dashboard->totals($reports)->rate('coverage')['value']);
        $this->expectException(LogicException::class);
        new StatisticsDashboard([$provider, $provider])->reports(new ReportQuery(new DateTimeImmutable('yesterday'), new DateTimeImmutable('tomorrow')));
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
