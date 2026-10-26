<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use RuntimeException;
use Throwable;

/** An audio track whose media file cannot be used, reported on its spec path. */
final class AudioAssetException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $path,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
