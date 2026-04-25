<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'cache' => [
            'pools' => [
                'cache.page_scanner' => [
                    'adapter' => 'cache.adapter.filesystem',
                    'default_lifetime' => 86400,
                ],
            ],
        ],
    ]);
};
