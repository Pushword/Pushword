<?php

return [
    'security' => [
        'role_hierarchy' => [
            'ROLE_EDITOR' => [
                5 => 'ROLE_CONVERSATION_ADMIN_ALL',
            ],
        ],
    ],
];
