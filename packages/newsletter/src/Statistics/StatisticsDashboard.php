<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class StatisticsDashboard
{
    /** @param iterable<StatisticsProvider> $providers */
    public function __construct(#[AutowireIterator('pushword.newsletter.statistics_provider')] private iterable $providers)
    {
    }

    /** @return list<Report> */
    public function reports(ReportQuery $query): array
    {
        $reports = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->reports($query) as $report) {
                $key = json_encode([$report->source, $report->id], \JSON_THROW_ON_ERROR);
                if (isset($reports[$key])) {
                    throw new LogicException('Duplicate newsletter report identity: '.$key);
                }

                $reports[$key] = $report;
            }
        }

        $reports = array_values($reports);
        usort($reports, static fn (Report $a, Report $b): int => $b->date <=> $a->date ?: strcmp($a->source.':'.$a->id, $b->source.':'.$b->id));

        return $reports;
    }

    /** @param list<Report> $reports */
    public function totals(array $reports): Report
    {
        $metrics = [];
        foreach (Report::METRICS as $name) {
            $metrics[$name] = Metric::sum(array_map(static fn (Report $report): Metric => $report->metric($name), $reports));
        }

        return new Report(
            'all',
            'all',
            'All sources',
            '',
            null,
            $metrics,
            qualificationMethod: implode(', ', array_unique(array_column($reports, 'qualificationMethod'))),
            trackingScope: implode('; ', array_unique(array_column($reports, 'trackingScope'))),
            mature: array_all($reports, static fn (Report $report): bool => $report->mature),
            ratesAvailable: array_all($reports, static fn (Report $report): bool => $report->ratesAvailable),
        );
    }
}
