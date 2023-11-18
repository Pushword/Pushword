<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Pushword\AdvancedMainImage\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.\Pushword\Core\PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);
};
