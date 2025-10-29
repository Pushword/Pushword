<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'paths' => [
            '%vendor_dir%/sonata-project/admin-bundle/src/Resources/views' => 'SonataAdmin',
            '%pw.package_dir%/admin/src/templates' => 'pwAdmin',
        ],
    ]);
};
