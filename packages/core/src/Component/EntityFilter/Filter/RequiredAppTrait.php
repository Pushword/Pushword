<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\App\AppConfig;

trait RequiredAppTrait
{
    private AppConfig $app;

    public function setApp(AppConfig $app): void
    {
        $this->app = $app;
    }

    public function getApp(): AppConfig
    {
        return $this->app;
    }
}
