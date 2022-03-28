<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Admin\AdminInterface;
use Symfony\Contracts\EventDispatcher\Event as SfEvent;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 *
 * @template T of object
 */
class Event extends SfEvent
{
    /**
     * @var string
     */
    public const NAME = 'pushword.admin.load_field';

    /**
     * @param AdminInterface<T> $admin
     * @param mixed[]           $fields
     */
    public function __construct(private AdminInterface $admin, private array $fields)
    {
    }

    /**
     * @return AdminInterface<T>
     */
    public function getAdmin(): AdminInterface
    {
        return $this->admin;
    }

    /**
     * @return mixed[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param mixed[] $fields
     *
     * @return self<T>
     */
    public function setFields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }
}
