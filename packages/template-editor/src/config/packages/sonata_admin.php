<?php

return [
    'sonata_admin' => [
        'dashboard' => [
            'groups' => [
                'app.admin.group.setting' => [
                    'label' => 'admin.label.params',
                    'label_catalogue' => 'messages',
                    'icon' => '<i class="fa fa-wrench"></i>',
                    'items' => [
                        0 => [
                            'route' => 'pushword_template_editor_list',
                            'label' => 'admin.label.theme',
                            'roles' => [
                                0 => 'ROLE_PIEDWEB_ADMIN_THEME',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
