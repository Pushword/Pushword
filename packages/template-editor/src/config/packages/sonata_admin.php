<?php

return [
    'sonata_admin' => [
        'dashboard' => [
            'groups' => [
                'app.admin.group.template_editor' => [
                    'on_top' => true,
                    'label' => 'admin.label.theme',
                    'translation_domain' => 'messages',
                    'icon' => '<i class="fa fa-code"></i>',
                    'roles' => [
                        0 => 'ROLE_PUSHWORD_ADMIN_THEME',
                    ],
                    'items' => [
                        [
                            'route' => 'pushword_template_editor_list',
                            'label' => 'admin.label.theme', // duplicate !
                        ],
                    ],
                ],
            ],
        ],
    ],
];
