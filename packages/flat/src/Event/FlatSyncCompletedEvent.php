<?php

namespace Pushword\Flat\Event;

use Symfony\Contracts\EventDispatcher\Event;

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
