<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('flysystem', [
        'storages' => [
            'pushword.mediaStorage' => [
                'adapter' => 'local',
                'options' => [
                    'directory' => '%pw.media_dir%',
                ],
            ],
        ],
    ]);
};
