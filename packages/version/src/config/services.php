<?php

use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Pushword\Core\PushwordCoreBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$storageDir', '%pw.pushword_version.storage_dir%');

    $services->load('Pushword\Version\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Admin',
        ]);

    // Admin journal (CRUD + menu) is optional: only wire it when the admin
    // bundle is installed, mirroring the snippet bundle's pattern.
    if (class_exists(AdminMenuItemsEvent::class)) {
        $services->load('Pushword\Version\Admin\\', __DIR__.'/../Admin');
    }
};
