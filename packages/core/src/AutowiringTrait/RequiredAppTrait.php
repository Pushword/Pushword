<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Component\App\AppConfig;

trait RequiredAppTrait
{
    private AppConfig $app;

    public function setApp(AppConfig $app): self
    {
        $this->app = $app;

        return $this;
    }

    public function getApp(): AppConfig
    {
        return $this->app;
    }
}
