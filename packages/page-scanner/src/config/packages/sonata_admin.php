<?php

return [
    'sonata_admin' => [
        'dashboard' => [
            'groups' => [
                'app.admin.group.static' => [
                    'keep_open' => true,
                    'label' => 'admin.label.manage',
                    'translation_domain' => 'messages',
                    'icon' => '<i class="fa fa-bolt"></i>',
                    'items' => [
                        0 => [
                            'route' => 'pushword_page_scanner',
                            'label' => 'admin.label.check_content',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
