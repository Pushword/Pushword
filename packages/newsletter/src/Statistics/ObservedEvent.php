<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;

/** A dated source observation; unknown classification and link metadata stay unknown. */
final readonly class ObservedEvent
{
    public function __construct(
        public string $kind,
        public DateTimeImmutable $occurredAt,
        public ?bool $automated = null,
        public string $qualificationMethod = 'unclassified',
        public string $scope = 'unknown',
        public ?string $linkId = null,
        public ?string $url = null,
    ) {
        if (! \in_array($kind, ['delivered', 'deferred', 'bounced', 'click', 'unsubscribed', 'complaint'], true)
            || ! \in_array($scope, ['unknown', 'body', 'navigation', 'footer', 'system'], true)) {
            throw new InvalidArgumentException('Unknown newsletter observation kind.');
        }
    }
}
