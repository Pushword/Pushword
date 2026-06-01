<?php

namespace Pushword\Api\Service;

use RuntimeException;

/**
 * Thrown by {@see BodyPatcher} when an edit cannot be applied unambiguously.
 * Carries the failing edit index, the reason (`not_found`|`ambiguous`) and the
 * number of matches found, so the controller can build a precise 422 response.
 */
final class BodyPatchException extends RuntimeException
{
    public function __construct(
        public readonly int $index,
        public readonly string $reason,
        public readonly int $matches,
    ) {
        parent::__construct(\sprintf('Edit %d failed: %s (%d matches)', $index, $reason, $matches));
    }
}
