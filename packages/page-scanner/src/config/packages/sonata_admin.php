<?php

return [
    'sonata_admin' => [
        'dashboard' => [
            'groups' => [
                'app.admin.group.static' => [
                    'keep_open' => true,
                    'label' => 'admin.label.manage',
                    'label_catalogue' => 'messages',
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
