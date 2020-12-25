<?php

namespace Pushword\Core\Component\EntityFilter;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
final class FilterEvent extends Event
{
    /**
     * @var string
     */
    public const NAME_BEFORE = 'pushword.entity_filter.before_filtering';

    /**
     * @var string
     */
    public const NAME_AFTER = 'pushword.entity_filter.after_filtering';

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
