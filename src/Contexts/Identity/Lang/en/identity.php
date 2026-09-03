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

];
