<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Admin\AdminInterface;
use Symfony\Contracts\EventDispatcher\Event as SfEvent;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
class Event extends SfEvent
{
    public const NAME = 'pushword.admin.load_field';

    private AdminInterface $admin;

    private array $fields;

    public function __construct(AdminInterface $admin, array $fields)
    {
        $this->admin = $admin;
        $this->fields = $fields;
    }

    public function getAdmin(): AdminInterface
    {
        return $this->admin;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }
}
