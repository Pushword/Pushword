<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppConfig;
use Twig\Environment as Twig;

trait EmailTwigTrait
{
    abstract public function getApp(): AppConfig;

}
