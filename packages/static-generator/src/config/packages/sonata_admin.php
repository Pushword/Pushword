<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('sonata_admin', [
        'dashboard' => [
            'groups' => [
                'app.admin.group.static' => [
                    'on_top' => true,
                    'label' => 'admin.label.manage',
                    'translation_domain' => 'messages',
                    'icon' => '<i class="fa fa-bolt"></i>',
                    'items' => [
                        0 => [
                            'route' => 'piedweb_static_generate',
                            'label' => 'admin.label.update',
                        ],
                    ],
                ],
            ],
        ],
    ]);
};
