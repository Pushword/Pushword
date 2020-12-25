<?php

return [
    'sonata_admin' => [
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
    ],
];
