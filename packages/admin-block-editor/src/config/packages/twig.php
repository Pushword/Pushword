<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'form_themes' => [
            '@PushwordAdminBlockEditor/editorjs_widget.html.twig',
        ],
    ]);
};

// #paths: "%pw.package_dir%/admin-block-editor/src/templates": Pushword # Permit to access component defined here and in core
