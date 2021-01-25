<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

trait RequiredEntityTrait
{
    private object $entity;

    public function setEntity(object $entity): void
    {
        $this->entity = $entity;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }
}
