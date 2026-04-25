<?php

namespace Pushword\Core\Component\EntityFilter;

use Pushword\Core\Event\PushwordEvents;
use Symfony\Contracts\EventDispatcher\Event;

final class FilterEvent extends Event
{
    public const string NAME_BEFORE = PushwordEvents::FILTER_BEFORE;

    public const string NAME_AFTER = PushwordEvents::FILTER_AFTER;

    public function __construct(
        private readonly Manager $manager,
        private readonly string $property
    ) {
    }

    public function getManager(): Manager
    {
        return $this->manager;
    }

    public function getProperty(): string
    {
        return $this->property;
    }
}
