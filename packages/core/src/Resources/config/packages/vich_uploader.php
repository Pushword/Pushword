<?php

declare(strict_types=1);

use Pushword\Core\Service\VichUploadPropertyNamer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('vich_uploader', [
        'db_driver' => 'orm',
        'mappings' => [
            'media_media' => [
                'upload_destination' => '%pw.media_dir%',
                'namer' => [
                    'service' => VichUploadPropertyNamer::class,
                ],
            ],
        ],
    ]);
};
