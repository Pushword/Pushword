<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Component\App\AppPool;
use Symfony\Contracts\Service\Attribute\Required;

trait RequiredApps
{
    protected AppPool $apps;

    #[Required]
    public function setApps(AppPool $appPool): void
    {
        $this->apps = $appPool;
    }
}
