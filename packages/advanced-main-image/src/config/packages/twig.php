<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'paths' => [
            '%pw.package_dir%/advanced-main-image/src/templates' => 'Pushword',
            // this was moved in post install since 1.0.0-rc80 because something breaks the order
            // -- quick and dirty fix
        ],
    ]);
};
