<?php

namespace Pushword\Core\Component\EntityFilter;

interface ManagerPoolInterface
{
    /** @return mixed */
    public function getProperty(object $entity, string $property = '');

    public function getManager(object $entity): Manager;
}
