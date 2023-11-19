<?php

use Pushword\Conversation\Admin\ConversationAdmin;

return [
    'security' => [
        'role_hierarchy' => [
            'ROLE_EDITOR' => [
                5 => 'ROLE_'.strtoupper(ConversationAdmin::class).'_ALL',
            ],
        ],
    ],
];
