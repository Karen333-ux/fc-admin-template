<?php

declare(strict_types=1);

return [

    'events' => [

        'user' => [
            'created' => 'User created',
            'updated' => 'User updated',
            'deleted' => 'User deleted',
            'restored' => 'User restored',
            'role_attached' => 'Role assigned to user',
            'role_detached' => 'Role removed from user',
            'force_logout' => 'Other sessions terminated',
            'device_terminated' => 'Device session terminated',
            'impersonation_started' => 'Impersonation started',
            'impersonation_stopped' => 'Impersonation stopped',
        ],

        'settings' => [
            'updated' => 'Settings updated',
        ],

        'auth' => [
            'login_failed' => 'Failed login attempt',
        ],

    ],

];
