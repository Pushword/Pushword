<?php

declare(strict_types=1);

use Pushword\Admin\PushwordAdminBundle;
use Pushword\Core\PushwordCoreBundle;
use Pushword\StaticGenerator\Cache\MessageHandler\PageCacheRefreshHandler;
use Pushword\StaticGenerator\Cache\PageCacheInvalidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Messenger\MessageBusInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$kernel', service('kernel'))
        ->bind('$projectDir', '%kernel.project_dir%');

    $adminInstalled = class_exists(PushwordAdminBundle::class);
    $adminExclude = $adminInstalled ? [] : [__DIR__.'/../Cache/Admin/'];

    $messengerInstalled = interface_exists(MessageBusInterface::class);
    $messengerExclude = $messengerInstalled ? [] : [
        __DIR__.'/../Cache/MessageHandler/',
        __DIR__.'/../Cache/PageCacheInvalidator.php',
    ];

    $services->load('Pushword\StaticGenerator\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            ...$adminExclude,
            ...$messengerExclude,
        ]);

    if ($messengerInstalled) {
        $services->set(PageCacheInvalidator::class);
        $services->set(PageCacheRefreshHandler::class);
    }
};
