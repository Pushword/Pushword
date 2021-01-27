<?php

use Pushword\Admin\PageAdminInterface;

return [
    'sonata_admin' => [
        'security' => [
            'handler' => 'sonata.admin.security.handler.role',
            'role_admin' => 'ROLE_ADMIN',
            'role_super_admin' => 'ROLE_SUPER_ADMIN',
        ],
        'title' => 'Admin',
        'title_logo' => '/bundles/pushwordadmin/logo.svg',
        'dashboard' => [
            'blocks' => null, //- { type: sonata.admin.block.admin_list, position: left }
            'groups' => [
                'app.admin.group.page' => [
                    'label' => 'admin.label.content',
                    'label_catalogue' => 'messages',
                    'items' => [
                        0 => PageAdminInterface::class,
                        1 => 'pushword.admin.media',
                    ],
                ],
                'app.admin.group.setting' => [
                    'label' => 'admin.label.params',
                    'label_catalogue' => 'messages',
                    'icon' => '<i class="fa fa-wrench"></i>',
                    'items' => [
                        0 => 'pushword.admin.user',
                    ],
                ],
            ],
        ],
        'options' => [
            'lock_protection' => true,
        ],
        'templates' => [
            'layout' => '@pwAdmin/layout.html.twig',
            'knp_menu_template' => '@pwAdmin/Menu/menu.html.twig',
            'user_block' => '@pwAdmin/user_block.html.twig',
        ],
    ],
    'sonata_doctrine_orm_admin' => [
        'templates' => [
            'form' => [
                0 => '@pwAdmin/Form/sd_form_admin_fields.html.twig',
            ],
        ],
    ],
    'sonata_block' => [
        'blocks' => [
            'sonata.admin.block.admin_list' => [
                'contexts' => [
                    0 => 'admin',
                ],
            ],
            'sonata.admin.block.search_result' => [
                'contexts' => [
                    0 => 'admin',
                ],
            ],
        ],
    ],
];
