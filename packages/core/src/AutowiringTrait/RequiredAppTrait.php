<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Component\App\AppConfig;

trait RequiredAppTrait
{
    private AppConfig $app;

    public function setApp(AppConfig $appConfig): self
    {
        $this->app = $appConfig;

        return $this;
    }

    public function getApp(): AppConfig
    {
        return $this->app;
    }
}
