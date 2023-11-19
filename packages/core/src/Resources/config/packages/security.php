<?php

use Pushword\Admin\MediaAdmin;
use Pushword\Admin\PageAdmin;
use Pushword\Admin\PageCheatSheetAdmin;
use Pushword\Admin\PageRedirectionAdmin;

return [
    'security' => [
        'password_hashers' => [
            '%pw.entity_user%' => [
                'algorithm' => 'auto',
            ],
        ],
        'role_hierarchy' => [
            'ROLE_EDITOR' => [
                0 => 'ROLE_USER',
                1 => 'ROLE_'.strtoupper(PageAdmin::class).'_ALL',
                2 => 'ROLE_'.strtoupper(PageRedirectionAdmin::class).'_ALL',
                3 => 'ROLE_'.strtoupper(PageCheatSheetAdmin::class).'_ALL',
                4 => 'ROLE_'.strtoupper(MediaAdmin::class).'_ALL',
            ],
            'ROLE_ADMIN' => [
                0 => 'ROLE_EDITOR',
                1 => 'ROLE_PUSHWORD_ADMIN',
                2 => 'ROLE_PUSHWORD_ADMIN_THEME',
            ],
            'ROLE_SUPER_ADMIN' => [
                0 => 'ROLE_ADMIN',
                1 => 'ROLE_ALLOWED_TO_SWITCH',
            ],
        ],
        'access_decision_manager' => [
            'strategy' => 'unanimous',
        ],
        'providers' => [
            'pushword_user_provider' => [
                'entity' => [
                    'class' => '%pw.entity_user%',
                    'property' => 'email',
                ],
            ],
        ],
        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                'security' => false,
            ],
            'main' => [
                'lazy' => true,
                'http_basic' => [
                    'realm' => 'Secured Area',
                ],
                'custom_authenticator' => \Pushword\Core\Security\LoginFormAuthenticator::class,
                'entry_point' => \Pushword\Core\Security\LoginFormAuthenticator::class,
                'logout' => [
                    'path' => 'pushword_logout',
                ],
                'remember_me' => [
                    'lifetime' => 31_536_000,
                    'always_remember_me' => true,
                    'secret' => '%kernel.secret%',
                ],
            ],
        ],
    ],
];
