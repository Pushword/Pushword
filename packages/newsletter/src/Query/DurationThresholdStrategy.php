<?php

namespace Pushword\Newsletter\Query;

use DateTimeImmutable;
use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;
use Pushword\Newsletter\Segment\SegmentCriteria;

/**
 * A date column compared against a duration rather than a date.
 *
 * A segment is written once and read again on every tick, so it says "older than
 * 7 days" and never "before July 3rd". The instant that duration points back to
 * is fixed for the whole rule — {@see \Pushword\Newsletter\Segment\SegmentResolver}
 * takes `now` once — so two conditions in the same rule cannot straddle a
 * boundary and contradict each other.
 */
final readonly class DurationThresholdStrategy implements FieldStrategy
{
    public function __construct(
        private string $column,
        private DateTimeImmutable $now,
    ) {
    }

    public function operators(): array
    {
        return ['olderThan', 'newerThan'];
    }

    public function compile(FieldCompilation $compilation): string
    {
        return $compilation->bind(
            \sprintf(
                '%s %s :%s',
                $compilation->column($this->column),
                'olderThan' === $compilation->operator ? '<=' : '>=',
                $compilation->parameter,
            ),
            SegmentCriteria::threshold($compilation->stringValue(), $this->now),
        );
    }
}
