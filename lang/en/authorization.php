<?php

declare(strict_types=1);

return [

    'actions' => [
        'view_any' => 'View any',
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'delete_any' => 'Delete any',
        'restore' => 'Restore',
        'force_delete' => 'Force delete',
        'export' => 'Export',
        'impersonate' => 'Impersonate',
        'reset_password' => 'Reset password',
        'force_logout' => 'Force logout',
        'manage_general' => 'Manage general settings',
        'manage_storage' => 'Manage storage',
        'manage_mail' => 'Manage mail',
        'manage_appearance' => 'Manage appearance',
        'prune' => 'Prune old records',
    ],

    'resources' => [
        'users' => 'Users',
        'settings' => 'Settings',
        'activity_logs' => 'Activity log',
    ],

    'groups' => [
        'identity' => 'Identity & users',
        'system' => 'System',
    ],

    'pages' => [
        'access.panel.admin' => 'Access the admin panel',
        'access.dashboard' => 'View the dashboard',
        'require.two_factor' => 'Required to enable two-factor authentication',
    ],

    'widgets' => [],

    'denied' => [
        'record_not_found' => 'That record does not exist.',
        'missing_permission' => 'You do not have the ":permission" permission.',
        'self_target' => 'You cannot perform this action on your own account.',
        'record_trashed' => 'This record is deleted — restore it first.',
    ],

];
