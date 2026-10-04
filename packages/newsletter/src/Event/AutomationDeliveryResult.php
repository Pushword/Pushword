<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Event;

use Pushword\Newsletter\Entity\AutomationDelivery;
use Pushword\Newsletter\Entity\Enrollment;

/** Transport outcome, before advancement; contains no body or late placeholders. */
final readonly class AutomationDeliveryResult
{
    public function __construct(
        public Enrollment $enrollment,
        public AutomationDelivery $delivery,
    ) {
    }
}
