<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

/** Completeness is established by the provider for this cohort, query and observation window. */
final readonly class ObservationCoverage
{
    /** @param array<value-of<Report::METRICS>, bool> $completeMetrics */
    public function __construct(public array $completeMetrics = [])
    {
    }

    public function complete(string $metric): bool
    {
        return $this->completeMetrics[$metric] ?? false;
    }
}
