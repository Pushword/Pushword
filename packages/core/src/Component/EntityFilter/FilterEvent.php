<?php

namespace Pushword\Core\Component\EntityFilter;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
final class FilterEvent extends Event
{
    public const NAME_BEFORE = 'pushword.entity_filter.before_filtering';
    public const NAME_AFTER = 'pushword.entity_filter.after_filtering';

    private Manager $manager;
    private string $property;

    public function __construct(Manager $manager, string $property)
    {
        $this->manager = $manager;
        $this->property = $property;
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
