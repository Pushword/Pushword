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
            __DIR__.'/../../src/DependencyInjection/',
            __DIR__.'/../../src/FormField/',
            __DIR__.'/../../src/Resources/',
            __DIR__.'/../../src/config/',
        ]);
};
