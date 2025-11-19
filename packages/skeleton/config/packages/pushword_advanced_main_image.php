<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('pushword_advanced_main_image', [
        'main_image_formats' => [
            'admin.page.mainImageFormat.none' => 1,
            'admin.page.mainImageFormat.normal' => 0,
            'admin.page.mainImageFormat.13fullscreen' => 2,
        ],
    ]);
};
