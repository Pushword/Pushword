<?php

namespace Pushword\Core\Component\EntityFilter;

use Pushword\Core\Entity\SharedTrait\IdInterface;

/**
 * @template T of object
 */
interface ManagerPoolInterface
{
    public function getProperty(IdInterface $id, string $property = ''): mixed;

    /**
     * @return Manager<T>
     */
    public function getManager(IdInterface $id): Manager;
}
