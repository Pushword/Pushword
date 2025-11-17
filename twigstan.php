<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;

return TwigStanConfig::configure(__DIR__)
    ->reportUnmatchedIgnoredErrors(true)
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.dist.neon')
    ->phpstanMemoryLimit(false)
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php')
    ->twigPaths(__DIR__ . '/packages/core/src/templates')
    ->phpPaths(__DIR__ . '/packages/core/src')
    ->create();
