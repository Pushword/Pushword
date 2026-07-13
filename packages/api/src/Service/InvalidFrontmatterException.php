<?php

namespace Pushword\Api\Service;

use RuntimeException;

/**
 * Thrown by {@see PageFrontmatterMapper} when a converter-backed frontmatter
 * value cannot be resolved (e.g. `mainImageFormat: "None"` — an unknown label
 * for an integer-backed property). The controller maps it to a 422 so the
 * client gets a clear error instead of the invalid value being stored and
 * crashing at render time.
 */
final class InvalidFrontmatterException extends RuntimeException
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
    ) {
        parent::__construct(\sprintf('Invalid value %s for frontmatter key "%s".', json_encode($value), $key));
    }
}
