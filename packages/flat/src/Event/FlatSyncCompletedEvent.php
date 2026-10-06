<?php

declare(strict_types=1);

namespace Pushword\Flat\Event;

use Symfony\Contracts\EventDispatcher\Event;

/** @api */
final class FlatSyncCompletedEvent extends Event
{
    public function __construct(
        private readonly string $host,
    ) {
    }

    public function getHost(): string
    {
        return $this->host;
    }
}
