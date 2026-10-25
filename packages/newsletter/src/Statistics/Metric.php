<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use InvalidArgumentException;

/** A missing observation is never the measured value zero. */
final readonly class Metric
{
    public function __construct(public ?int $value, public string $state = 'measured')
    {
        if (! \in_array($state, ['measured', 'notMeasured', 'unavailable'], true) || ('measured' === $state) !== (null !== $value) || $value < 0) {
            throw new InvalidArgumentException('Invalid newsletter measurement.');
        }
    }

    public static function notMeasured(): self
    {
        return new self(null, 'notMeasured');
    }

    public static function unavailable(): self
    {
        return new self(null, 'unavailable');
    }

    /** @param list<self> $metrics */
    public static function sum(array $metrics): self
    {
        $value = 0;
        $state = 'measured';
        foreach ($metrics as $metric) {
            $value += $metric->value ?? 0;
            if ('unavailable' === $metric->state || ('measured' === $state && 'notMeasured' === $metric->state)) {
                $state = $metric->state;
            }
        }

        return new self('measured' === $state ? $value : null, $state);
    }
}
