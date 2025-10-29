<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('sonata_admin', [
        'dashboard' => [
            'groups' => [
                'app.admin.group.page_scanner' => [
                    'on_top' => true,
                    'keep_open' => true,
                    'label' => 'admin.label.check_content',
                    'translation_domain' => 'messages',
                    'icon' => '<i class="fa fa-check-circle"></i>',
                    // 'extras' => [PageMenuProvider::ORDER_NUMBER, 2],
                    'items' => [
                        0 => [
                            'route' => 'pushword_page_scanner',
                            'label' => 'admin.label.check_content',
                        ],
                    ],
                ],
            ],
        ],
    ]);
};
