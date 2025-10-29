<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'paths' => [
            '%pw.package_dir%/advanced-main-image/src/../src/templates' => 'Pushword',
        ],
    ]);
};
