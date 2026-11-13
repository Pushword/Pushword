<?php

declare(strict_types=1);

namespace Pushword\TwigStan;

use TwigStan\Config\ConfigBuilder;
use TwigStan\Config\TwigStanConfig;

final class Config
{
    public static function configure(string $projectDirectory, string $environmentLoader): ConfigBuilder
    {
        $fingerprint = hash('sha256', hash_file('sha256', Runtime::toolDirectory().'/patches.lock.json').'|'.hash_file('sha256', __DIR__.'/ResolveViewReturnTypeExtension.php').'|'.(getenv('PUSHWORD_TWIGSTAN_HOST') ?: 'default'));

        return TwigStanConfig::configure($projectDirectory)
            ->phpstanBinPath(Runtime::toolDirectory().'/phpstan.php')
            ->phpstanConfigurationFile(__DIR__.'/../config/phpstan.neon')
            ->phpstanMemoryLimit('1G')
            ->twigEnvironmentLoader($environmentLoader)
            ->tempDirectory(Runtime::cacheDirectory().'/'.$fingerprint);
    }
}
