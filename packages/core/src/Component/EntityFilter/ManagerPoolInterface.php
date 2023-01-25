<?php

namespace Pushword\Core\Component\EntityFilter;

use Pushword\Core\Entity\SharedTrait\IdInterface;

/**
 * @template T of object
 */
interface ManagerPoolInterface
{
    /** @return mixed */
    public function getProperty(IdInterface $id, string $property = '');

    /**
     * @return Manager<T>
     */
    public function getManager(IdInterface $id): Manager;
}
