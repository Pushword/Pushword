<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('sonata_admin', [
        'assets' => [
            'extra_javascripts' => ['/bundles/pushwordadminblockeditor/admin-block-editor.js?8'],
            'extra_stylesheets' => ['/bundles/pushwordadminblockeditor/style.css?8'],
        ],
    ]);
};
