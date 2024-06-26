<?php

declare(strict_types=1);
use Pushword\Core\PushwordCoreBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Pushword\Admin\\', __DIR__.'/../../src/')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);
};
