<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'paths' => [
            '%pw.package_dir%/static-generator/src/templates' => 'PushwordStatic',
        ],
    ]);
};
