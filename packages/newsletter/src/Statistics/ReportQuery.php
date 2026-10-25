<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ReportQuery
{
    public DateTimeImmutable $asOf;

    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
        public ?int $audienceId = null,
        public string $basis = 'sent',
        public ?int $windowDays = null,
        public string $groupBy = 'campaign',
        ?DateTimeImmutable $asOf = null,
    ) {
        if ($from >= $until || ! \in_array($basis, ['sent', 'events'], true) || ! \in_array($windowDays, [null, 7, 30], true) || ! \in_array($groupBy, ['campaign', 'automation', 'step', 'occurrence'], true)) {
            throw new InvalidArgumentException('Invalid newsletter report selection.');
        }

        $this->asOf = $asOf ?? new DateTimeImmutable();
    }

    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->from && $date < $this->until && $date <= $this->asOf;
    }

    public function observes(DateTimeImmutable $sentAt, DateTimeImmutable $eventAt): bool
    {
        return $eventAt >= $sentAt && $eventAt <= $this->asOf
            && (null === $this->windowDays || $eventAt < $sentAt->modify('+'.$this->windowDays.' days'))
            && ('sent' === $this->basis || $this->contains($eventAt));
    }
}
