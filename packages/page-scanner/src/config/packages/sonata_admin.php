<?php

return [
    'sonata_admin' => [
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
    ],
];
