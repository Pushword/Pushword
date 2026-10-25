<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Provider adapters must honor selection and mark unsupported observations unavailable. */
#[AutoconfigureTag('pushword.newsletter.statistics_provider')]
interface StatisticsProvider
{
    /** @return iterable<Report> Report identity is the pair (source, id). */
    public function reports(ReportQuery $query): iterable;
}
