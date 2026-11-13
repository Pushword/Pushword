<?php

declare(strict_types=1);

use App\Entity\User;
use TwigStan\Config\TwigStanConfig;

$root = \dirname(__DIR__, 2);
$cacheKey = hash('sha256', hash_file('sha256', __DIR__.'/patches.lock.json').'|'.hash_file('sha256', __DIR__.'/ResolveViewReturnTypeExtension.php').'|'.(getenv('PUSHWORD_TWIGSTAN_HOST') ?: 'default'));

return TwigStanConfig::configure($root)
    ->phpstanBinPath(__DIR__.'/phpstan.php')
    ->phpstanConfigurationFile(__DIR__.'/phpstan.neon')
    ->phpstanMemoryLimit('1G')
    ->twigEnvironmentLoader(__DIR__.'/twig-loader.php')
    ->tempDirectory(__DIR__.'/var/'.$cacheKey)
    ->appUserClass(User::class)
    ->phpPaths(...glob($root.'/packages/*/src', \GLOB_ONLYDIR))
;
