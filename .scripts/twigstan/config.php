<?php

declare(strict_types=1);

use App\Entity\User;
use Pushword\TwigStan\Config;

$root = \dirname(__DIR__, 2);

return Config::configure($root, __DIR__.'/twig-loader.php')
    ->phpstanConfigurationFile(__DIR__.'/phpstan.neon')
    ->appUserClass(User::class)
    ->phpPaths(...glob($root.'/packages/*/src', \GLOB_ONLYDIR))
;
