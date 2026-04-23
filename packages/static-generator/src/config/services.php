<?php

use Pushword\Admin\PushwordAdminBundle;
use Pushword\Core\PushwordCoreBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$kernel', service('kernel'))
        ->bind('$projectDir', '%kernel.project_dir%');

    $adminInstalled = class_exists(PushwordAdminBundle::class);
    $adminExclude = $adminInstalled ? [] : [__DIR__.'/../Cache/Admin/'];

    $services->load('Pushword\StaticGenerator\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            ...$adminExclude,
        ]);
};
