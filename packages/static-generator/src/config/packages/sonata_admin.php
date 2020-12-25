<?php

return [
    'sonata_admin' => [
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
    ],
];
