<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Component\EntityFilter\ManagerPool;

/**
 * @template T of object
 */
trait RequiredManagerPoolTrait
{
    /**
     * @var ManagerPool<T>
     */
    private ManagerPool $entityFilterManagerPool;

    /**
     * @param ManagerPool<T> $entityFilterManagerPool
     */
    public function setManagerPool(ManagerPool $entityFilterManagerPool): void
    {
        $this->entityFilterManagerPool = $entityFilterManagerPool;
    }

    /**
     * @return ManagerPool<T>
     */
    public function getManagerPool(): ManagerPool
    {
        return $this->entityFilterManagerPool;
    }
}
