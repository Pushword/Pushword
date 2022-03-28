<?php

namespace Pushword\Core\Component\EntityFilter;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 *
 * @template T of object
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

    /**
     * @param Manager<T> $manager
     */
    public function __construct(private Manager $manager, private string $property)
    {
    }

    /**
     * @return Manager<T>
     */
    public function getManager(): Manager
    {
        return $this->manager;
    }

    public function getProperty(): string
    {
        return $this->property;
    }
}
