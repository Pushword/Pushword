<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use DateTimeImmutable;

/** Providers select the cohort; absent attempt references and recipient identities stay null. */
final readonly class ObservedMessage
{
    /** @param list<ObservedEvent> $events
     * @param list<string>|null $trackingScopes null means the source did not prove its instrumentation
     */
    public function __construct(
        public ?string $reference,
        public ?string $recipientKey,
        public ?DateTimeImmutable $attemptedAt,
        public ?DateTimeImmutable $acceptedAt = null,
        public ?bool $trackingAllowed = null,
        public bool $transportFailed = false,
        public array $events = [],
        public ?array $trackingScopes = null,
    ) {
    }
}
