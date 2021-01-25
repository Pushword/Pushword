<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Manager;

trait RequiredManagerTrait
{
    private Manager $entityFilterManager;

    public function setManager(Manager $entityFilterManager): void
    {
        $this->entityFilterManager = $entityFilterManager;
    }

    public function getManager(): Manager
    {
        return $this->entityFilterManager;
    }
}
