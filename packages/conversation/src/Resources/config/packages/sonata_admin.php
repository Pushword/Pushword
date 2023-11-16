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
                        [
                            'route' => 'admin_pushword_conversation_message_list',
                            'label' => 'admin.label.conversation', // duplicate !
                        ],
                    ],
                ],
            ],
        ],
    ],
];
