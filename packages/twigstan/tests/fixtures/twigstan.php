<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;

$tool = \dirname(__DIR__, 2).'/tools';

return TwigStanConfig::configure(__DIR__)
    ->phpstanBinPath($tool.'/vendor/bin/phpstan')
    ->phpstanConfigurationFile(__DIR__.'/phpstan.neon')
    ->twigEnvironmentLoader(__DIR__.'/twig-loader.php')
    ->tempDirectory(getenv('PUSHWORD_TWIGSTAN_TEST_CACHE') ?: __DIR__.'/.twigstan')
    ->phpPaths(__DIR__.'/src')
    ->twigPaths(__DIR__.'/templates')
    ->create();
