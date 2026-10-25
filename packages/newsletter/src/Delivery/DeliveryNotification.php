<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Delivery;

use DateTimeImmutable;
use Pushword\Newsletter\Enum\DeliveryEventKind;

/** Adapters authenticate the webhook before passing its normalized event here. */
final readonly class DeliveryNotification
{
    public function __construct(
        public string $source,
        public string $eventId,
        public string $messageReference,
        public DeliveryEventKind $kind,
        public DateTimeImmutable $occurredAt,
        public string $reason,
    ) {
    }
}
