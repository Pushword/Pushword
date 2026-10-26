<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use InvalidArgumentException;

/** A missing observation is never the measured value zero. */
final readonly class Metric
{
    public function __construct(public ?int $value, public string $state = 'measured', public bool $complete = true)
    {
        if (! \in_array($state, ['measured', 'notMeasured', 'unavailable'], true) || ('measured' === $state) !== (null !== $value) || $value < 0) {
            throw new InvalidArgumentException('Invalid newsletter measurement.');
        }
    }

    public static function notMeasured(): self
    {
        return new self(null, 'notMeasured', false);
    }

    public static function unavailable(): self
    {
        return new self(null, 'unavailable', false);
    }

    /** @param list<self> $metrics */
    public static function sum(array $metrics): self
    {
        $value = 0;
        $state = 'measured';
        $complete = true;
        foreach ($metrics as $metric) {
            $value += $metric->value ?? 0;
            $complete = $complete && $metric->complete;
            if ('unavailable' === $metric->state || ('measured' === $state && 'notMeasured' === $metric->state)) {
                $state = $metric->state;
            }
        }

        return new self('measured' === $state ? $value : null, $state, $complete);
    }
}
