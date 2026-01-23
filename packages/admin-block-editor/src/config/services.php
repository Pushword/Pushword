<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$editorBlockForNewPage', '%pw.pushword_admin_block_editor.new_page%')
        ->bind('$publicMediaDir', '%pw.public_media_dir%');

    $services->load('Pushword\AdminBlockEditor\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../{DependencyInjection,Entity,Migrations,Tests,config,Kernel.php,Installer/install.php}',
        ]);
};
