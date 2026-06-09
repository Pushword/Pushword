<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine', [
        'orm' => [
            'mappings' => [
                'PushwordVersionBundle' => [
                    'type' => 'attribute',
                    'dir' => 'Entity',
                    'alias' => 'PushwordVersion',
                ],
            ],
        ],
    ]);
};
