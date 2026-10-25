<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use DateTimeImmutable;

final readonly class Report
{
    public const array METRICS = ['accepted', 'delivered', 'transportFailed', 'bounced', 'deferred', 'unsubscribed', 'clicks', 'clickers', 'filteredClicks', 'filteredClickers', 'qualifiedClicks', 'trackedMessages', 'coverageKnownMessages'];

    /** @param array<string, Metric> $metrics */
    public function __construct(
        public string $source,
        public string $id,
        public string $label,
        public string $managementUrl,
        public ?DateTimeImmutable $date,
        public array $metrics,
        public string $kind = 'campaign',
        public string $qualificationMethod = 'unclassified',
        public string $trackingScope = 'body; navigation and footer excluded',
        public bool $mature = true,
        public bool $ratesAvailable = true,
    ) {
    }

    public function metric(string $name): Metric
    {
        return $this->metrics[$name] ?? Metric::unavailable();
    }

    /** @param 'clickRate'|'filteredClickRate'|'coverage'|'deliveryRate'|'bounceRate'|'unsubscribeRate' $name
     * @return array{numerator: Metric, denominator: Metric, value: float|null, state: string} */
    public function rate(string $name): array
    {
        [$numerator, $denominator] = match ($name) {
            'clickRate' => ['clickers', 'trackedMessages'],
            'filteredClickRate' => ['filteredClickers', 'trackedMessages'],
            'coverage' => ['trackedMessages', 'accepted'],
            'deliveryRate' => ['delivered', 'accepted'],
            'bounceRate' => ['bounced', 'accepted'],
            'unsubscribeRate' => ['unsubscribed', 'accepted'],
        };
        $top = $this->metric($numerator);
        $bottom = $this->metric($denominator);
        $state = Metric::sum([$top, $bottom])->state;
        if (! $this->ratesAvailable || 0 === $bottom->value) {
            $state = 'notMeasured';
        }

        $value = null;
        if ('measured' === $state && null !== $top->value && null !== $bottom->value && $bottom->value > 0) {
            $value = round(100 * $top->value / $bottom->value, 2);
        }

        return ['numerator' => $top, 'denominator' => $bottom, 'value' => $value, 'state' => $state];
    }
}
