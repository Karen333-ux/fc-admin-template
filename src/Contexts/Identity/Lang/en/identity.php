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

    // Users-specific empty state — the shared one lives in lang/*/table.php
    'empty' => [
        'heading' => 'No users yet',
        'description' => 'Start by adding the first user to this organisation.',
        'cta' => 'Add a user',
    ],

    'notifications' => [
        'invited' => [
            'subject' => 'You have been invited to an organisation',
            'greeting' => 'Hello,',
            'body' => 'You have been invited to join an organisation on the Future Code panel. Use the button below to finish signing up.',
            'short' => 'An invitation is waiting for you.',
            'cta' => 'Accept the invitation',
        ],
    ],

];
