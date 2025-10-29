<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('sonata_admin', [
        'dashboard' => [
            'groups' => [
                'app.admin.group.conversation' => [
                    'on_top' => true,
                    'label' => 'admin.label.conversation',
                    'translation_domain' => 'messages',
                    'icon' => '<i class="fa fa-comments"></i>',
                    'items' => [
                        0 => [
                            'route' => 'admin_conversation_list',
                            'label' => 'admin.label.conversation',
                        ],
                    ],
                ],
            ],
        ],
    ]);
};
