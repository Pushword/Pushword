<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Component\App\AppPool;

trait RequiredApps
{
    protected AppPool $apps;

    /** @Required */
    public function setApps(AppPool $apps): void
    {
        $this->apps = $apps;
    }
}
