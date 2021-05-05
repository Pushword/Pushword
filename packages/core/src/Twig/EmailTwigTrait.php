<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppConfig;

trait EmailTwigTrait
{
    abstract public function getApp(): AppConfig;
}
