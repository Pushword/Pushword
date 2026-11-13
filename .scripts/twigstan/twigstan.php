<?php

declare(strict_types=1);

use App\Entity\User;
use TwigStan\Config\TwigStanConfig;

$root = \dirname(__DIR__, 2);
$cacheKey = hash('sha256', hash_file('sha256', __DIR__.'/patches.lock.json').'|'.(getenv('PUSHWORD_TWIGSTAN_HOST') ?: 'default'));

return TwigStanConfig::configure($root)
    ->phpstanBinPath(__DIR__.'/phpstan.php')
    ->phpstanConfigurationFile(__DIR__.'/phpstan.neon')
    ->phpstanMemoryLimit('1G')
    ->twigEnvironmentLoader(__DIR__.'/twig-loader.php')
    ->tempDirectory(__DIR__.'/var/'.$cacheKey)
    ->appUserClass(User::class)
    ->phpPaths(...glob($root.'/packages/*/src', \GLOB_ONLYDIR))
    ->twigPaths(...array_merge(glob($root.'/packages/*/src/templates', \GLOB_ONLYDIR), [$root.'/packages/dev-app/templates', $root.'/packages/docs/templates']))
    ->create();
