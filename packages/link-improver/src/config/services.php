<?php

use Pushword\Admin\Crud\PageCrudExtensionInterface;
use Pushword\Core\PushwordCoreBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Pushword\LinkImprover\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Admin',
        ]);

    // The per-page panel (action + controller) is optional: only wire it when
    // the admin bundle is installed, mirroring the version bundle's pattern.
    if (interface_exists(PageCrudExtensionInterface::class)) {
        $services->load('Pushword\LinkImprover\Admin\\', __DIR__.'/../Admin');
    }
};
