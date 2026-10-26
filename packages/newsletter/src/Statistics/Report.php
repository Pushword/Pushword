<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use DateTimeImmutable;

final readonly class Report
{
    /** Frozen prefix of the 1.0.81 CSV format. New columns are appended. */
    public const array BASE_METRICS = ['accepted', 'delivered', 'transportFailed', 'bounced', 'deferred', 'unsubscribed', 'clicks', 'clickers', 'filteredClicks', 'filteredClickers', 'qualifiedClicks', 'trackedMessages', 'coverageKnownMessages'];

    public const array METRICS = [...self::BASE_METRICS, 'complaints', 'automatedClicks', 'unclassifiedClicks', 'clicksWithoutKnownBots', 'clickersWithoutKnownBots'];

    public const array BASE_RATES = [
        'clickRate' => ['clickers', 'trackedMessages'],
        'filteredClickRate' => ['filteredClickers', 'trackedMessages'],
        'coverage' => ['trackedMessages', 'accepted'],
        'deliveryRate' => ['delivered', 'accepted'],
        'bounceRate' => ['bounced', 'accepted'],
        'unsubscribeRate' => ['unsubscribed', 'accepted'],
    ];

    public const array RATES = [
        ...self::BASE_RATES,
        'clickRateDelivered' => ['clickers', 'delivered'],
        'filteredClickRateDelivered' => ['filteredClickers', 'delivered'],
        'clickRateWithoutKnownBots' => ['clickersWithoutKnownBots', 'trackedMessages'],
        'clickRateWithoutKnownBotsDelivered' => ['clickersWithoutKnownBots', 'delivered'],
    ];

    /** @param array<string, Metric> $metrics
     * @param array<key-of<self::RATES>, value-of<self::METRICS>|null> $rateDenominators overrides the default base; null makes only that rate unavailable
     * @param 'info'|'warning'|'danger'                                $noticeLevel
     * @param list<LinkReport>                                         $links
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
        public array $links = [],
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
        if ('measured' === $state && (! $top->complete || ! $bottom->complete)) {
            $state = 'unavailable';
        }

        if (! $this->ratesAvailable || (0 === $bottom->value && $bottom->complete)) {
            $state = 'notMeasured';
        }

        $value = null;
        if ('measured' === $state && null !== $top->value && null !== $bottom->value && $bottom->value > 0) {
            $value = round(100 * $top->value / $bottom->value, 2);
        }

        return ['numerator' => $top, 'denominator' => $bottom, 'value' => $value, 'state' => $state, 'numeratorMetric' => $numerator, 'denominatorMetric' => $denominator];
    }
}
