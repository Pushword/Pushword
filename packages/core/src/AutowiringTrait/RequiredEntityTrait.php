<?php

namespace Pushword\Core\AutowiringTrait;

trait RequiredEntityTrait
{
    private object $entity;

    public function setEntity(object $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }
}
