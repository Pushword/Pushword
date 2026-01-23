<?php

declare(strict_types=1);

use Pushword\Core\Service\VichUploadPropertyNamer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('vich_uploader', [
        'db_driver' => 'orm',
        'storage' => 'flysystem',
        'mappings' => [
            'media_media' => [
                'uri_prefix' => '/%pw.public_media_dir%',
                'upload_destination' => 'pushword.mediaStorage',
                'namer' => [
                    'service' => VichUploadPropertyNamer::class,
                ],
            ],
        ],
    ]);
};
