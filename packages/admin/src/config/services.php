<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$mediaClass', '%pw.entity_media%')
        ->bind('$pageClass', '%pw.entity_page%')
        ->bind('$userClass', '%pw.entity_user%');

    $services->load('Pushword\Admin\\', __DIR__.'/../../src/')
        ->exclude([
            __DIR__.'/../'.Pushword\Core\PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);
};
