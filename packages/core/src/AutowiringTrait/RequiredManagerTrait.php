<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Component\EntityFilter\Manager;

/**
 * @template T of object
 */
trait RequiredManagerTrait
{
    /**
     * @var Manager<T>
     */
    private Manager $entityFilterManager;

    /**
     * @param Manager<T> $entityFilterManager
     */
    public function setManager(Manager $entityFilterManager): void
    {
        $this->entityFilterManager = $entityFilterManager;
    }

    /**
     * @return Manager<T>
     */
    public function getManager(): Manager
    {
        return $this->entityFilterManager;
    }
}
