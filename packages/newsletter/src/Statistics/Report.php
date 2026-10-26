<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use DateTimeImmutable;

final readonly class Report
{
    public const array METRICS = ['accepted', 'delivered', 'transportFailed', 'bounced', 'deferred', 'unsubscribed', 'clicks', 'clickers', 'filteredClicks', 'filteredClickers', 'qualifiedClicks', 'trackedMessages', 'coverageKnownMessages'];

    public const array RATES = [
        'clickRate' => ['clickers', 'trackedMessages'],
        'filteredClickRate' => ['filteredClickers', 'trackedMessages'],
        'coverage' => ['trackedMessages', 'accepted'],
        'deliveryRate' => ['delivered', 'accepted'],
        'bounceRate' => ['bounced', 'accepted'],
        'unsubscribeRate' => ['unsubscribed', 'accepted'],
    ];

    /** @param array<string, Metric> $metrics
     * @param array<key-of<self::RATES>, value-of<self::METRICS>|null> $rateDenominators overrides the default base; null makes only that rate unavailable
     * @param 'info'|'warning'|'danger'                                $noticeLevel
     */
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
        public array $rateDenominators = [],
        public ?DateTimeImmutable $observedAt = null,
        public ?string $notice = null,
        public string $noticeLevel = 'info',
    ) {
    }

    public function metric(string $name): Metric
    {
        return $this->metrics[$name] ?? Metric::unavailable();
    }

    /** @param key-of<self::RATES> $name
     * @return array{numerator: Metric, denominator: Metric, value: float|null, state: string, numeratorMetric: value-of<self::METRICS>, denominatorMetric: value-of<self::METRICS>|null} */
    public function rate(string $name): array
    {
        [$numerator, $defaultDenominator] = self::RATES[$name];
        $denominator = \array_key_exists($name, $this->rateDenominators) ? $this->rateDenominators[$name] : $defaultDenominator;
        $top = $this->metric($numerator);
        $bottom = null === $denominator ? Metric::unavailable() : $this->metric($denominator);
        $state = Metric::sum([$top, $bottom])->state;
        if (! $this->ratesAvailable || 0 === $bottom->value) {
            $state = 'notMeasured';
        }

        $value = null;
        if ('measured' === $state && null !== $top->value && null !== $bottom->value && $bottom->value > 0) {
            $value = round(100 * $top->value / $bottom->value, 2);
        }

        return ['numerator' => $top, 'denominator' => $bottom, 'value' => $value, 'state' => $state, 'numeratorMetric' => $numerator, 'denominatorMetric' => $denominator];
    }
}
