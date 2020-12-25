<?php

declare(strict_types=1);

use Pushword\Core\PushwordCoreBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$publicDir', '%pw.public_dir%')
        ->bind('$kernel', service('kernel'))
        ->bind('$varDir', '%kernel.project_dir%/var')
        ->bind('$pageScanInterval', '%pw.pushword_page_scanner.min_interval_between_scan%')
        ->bind('$linksToIgnore', '%pw.pushword_page_scanner.links_to_ignore%');

    $services->load('Pushword\PageScanner\\', __DIR__.'/../*')
        ->exclude([__DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH]);
};
