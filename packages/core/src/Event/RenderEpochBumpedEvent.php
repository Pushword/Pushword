<?php

namespace Pushword\Core\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched by RenderEpoch::bump() after the epoch of one or more hosts changed.
 * Listeners react to "some rendered output is now stale for these hosts" without
 * the bump source having to know who regenerates it.
 */
final class RenderEpochBumpedEvent extends Event
{
    /**
     * @param string[] $hosts main hosts whose epoch changed
     */
    public function __construct(
        public readonly array $hosts,
    ) {
    }
}
