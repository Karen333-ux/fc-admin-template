<?php

declare(strict_types=1);

return [

    'resource' => [
        'singular' => 'User',
        'plural' => 'Users',
    ],

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'created_at' => 'Created at',
        'roles' => 'Roles',
    ],

    'actions' => [
        'activity_log' => 'Activity log',
        'impersonate' => 'Impersonate',
    ],

    'pages' => [
        'my_devices' => 'My devices',
    ],

    'devices' => [
        'fields' => [
            'device' => 'Device',
            'last_active_at' => 'Last active',
            'status' => 'Status',
        ],
        'unknown_device' => 'Unrecognised device',
        'current_device' => 'This device',
        'actions' => [
            'terminate' => 'Terminate',
            'terminate_others' => 'Terminate all other sessions',
        ],
        'notifications' => [
            'terminated' => 'Device session terminated.',
            'others_terminated' => 'All other sessions have been terminated.',
        ],
        'empty' => [
            'heading' => 'No other devices',
        ],
    ],

    // Users-specific empty state — the shared one lives in lang/*/table.php
    'empty' => [
        'heading' => 'No users yet',
        'description' => 'Start by adding the first user to this organisation.',
        'cta' => 'Add a user',
    ],

    'validation' => [
        'password_recently_used' => 'You cannot reuse one of your last 5 passwords.',
    ],

    'notifications' => [
        'invited' => [
            'subject' => 'You have been invited to an organisation',
            'greeting' => 'Hello,',
            'body' => 'You have been invited to join an organisation on the Future Code panel. Use the button below to finish signing up.',
            'short' => 'An invitation is waiting for you.',
            'cta' => 'Accept the invitation',
        ],
        'new_device' => [
            'subject' => 'New sign-in to your account',
            'greeting' => 'Hello,',
            'body' => 'Your account was just signed in from a new device: :device, IP :ip, on :time.',
            'warning' => 'If this was not you, change your password immediately and enable two-factor authentication.',
            'unknown_device' => 'an unrecognised device',
        ],
        'password_changed' => [
            'subject' => 'Your password has been changed',
            'greeting' => 'Hello,',
            'body' => 'The password for your account was just changed.',
            'warning' => 'If you did not make this change, contact an administrator immediately.',
        ],
        'repeated_failed_login_attempts' => [
            'subject' => 'Repeated failed sign-in attempts on your account',
            'greeting' => 'Hello,',
            'body' => 'There have been repeated failed sign-in attempts on your account from IP address :ip.',
            'warning' => 'If this was not you, change your password immediately and enable two-factor authentication.',
        ],
    ],

];
