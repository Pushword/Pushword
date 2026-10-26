<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Event;

use DateTimeImmutable;
use Pushword\Newsletter\Entity\DeliveryMessage;
use Pushword\Newsletter\Statistics\ReportQuery;
use Symfony\Contracts\EventDispatcher\Event;

/** An external collector may prove completeness before native delivery events are read. */
final class QualifyDeliveryCoverage extends Event
{
    public bool $complete = false;

    public ?DateTimeImmutable $observedAt = null;

    public ?string $notice = null;

    /** @var 'info'|'warning'|'danger' */
    public string $noticeLevel = 'warning';

    /** @param list<DeliveryMessage> $messages */
    public function __construct(public readonly array $messages, public readonly ReportQuery $query)
    {
    }
}
