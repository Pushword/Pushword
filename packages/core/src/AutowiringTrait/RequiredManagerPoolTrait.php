<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Component\EntityFilter\ManagerPool;

trait RequiredManagerPoolTrait
{
    private ManagerPool $entityFilterManagerPool;

    public function setManagerPool(ManagerPool $entityFilterManagerPool): void
    {
        $this->entityFilterManagerPool = $entityFilterManagerPool;
    }

    public function getManagerPool(): ManagerPool
    {
        return $this->entityFilterManagerPool;
    }
}
