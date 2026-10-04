<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Event;

use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\Enrollment;
use Symfony\Contracts\EventDispatcher\Event;

/** Dispatched only at synchronous drip delivery, never at enrollment or preview. */
final class PrepareAutomationDelivery extends Event
{
    public readonly Automation $automation;

    public readonly Contact $contact;

    public readonly int $subjectId;

    /** @var array<string, string> Values used only in the body, never persisted or logged. */
    public array $placeholders = [];

    /** @var list<string> Absolute URLs excluded from both UTM and click rewriting. */
    public array $untrackedUrls = [];

    public private(set) ?string $vetoReason = null;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly AutomationStep $step,
    ) {
        $this->automation = $enrollment->automation;
        $this->contact = $enrollment->contact;
        $this->subjectId = $enrollment->subjectId;
    }

    public function veto(string $reason): void
    {
        $this->vetoReason = $reason;
        $this->stopPropagation();
    }
}
