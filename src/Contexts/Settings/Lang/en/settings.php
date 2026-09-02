<?php

declare(strict_types=1);

return [

    'pages' => [
        'appearance' => 'Appearance',
        'general' => 'General settings',
        'mail' => 'Mail',
        'storage' => 'Storage',
    ],

    'sections' => [
        'brand' => 'Brand',
        'brand_help' => 'Colours and fonts used across the whole panel.',
        'layout' => 'Layout',
        'identity' => 'Application identity',
        'identity_help' => 'The name and description shown across the panel and in emails.',
        'contact' => 'Support details',
        'localization' => 'Language & time',
        'maintenance' => 'Maintenance mode',
        'maintenance_help' => 'When enabled, this message replaces the content.',
        'mail_transport' => 'Outgoing server',
        'mail_transport_help' => 'Changes here affect the next message with no redeploy.',
        'mail_sender' => 'Sender details',
        'storage_disks' => 'Disks',
        'storage_disks_help' => 'Options come from `config/filesystems.php` — no disk names are written in code.',
        'storage_collections' => 'Private collections',
        'storage_collections_help' => 'Files in these collections go to the private disk and only get expiring links.',
        'storage_uploads' => 'Upload limits',
    ],

    'fields' => [
        'primary_color' => 'Primary colour',
        'font' => 'Font',
        'default_theme' => 'Default theme',
        'allow_theme_switch' => 'Allow theme switching',
        'sidebar_default' => 'Sidebar',
        'app_name' => 'Application name',
        'app_description' => 'Application description',
        'support_email' => 'Support email',
        'support_phone' => 'Support phone',
        'default_locale' => 'Default language',
        'timezone' => 'Timezone',
        'maintenance_mode' => 'Enable maintenance mode',
        'maintenance_message' => 'Maintenance message',
        'mail_driver' => 'Transport',
        'mail_host' => 'Host',
        'mail_port' => 'Port',
        'mail_username' => 'Username',
        'mail_password' => 'Password',
        'mail_encryption' => 'Encryption',
        'mail_from_address' => 'From address',
        'mail_from_name' => 'From name',
        'default_disk' => 'Default disk',
        'private_disk' => 'Private files disk',
        'private_collections' => 'Private collections',
        'max_upload_size_kb' => 'Maximum file size (KB)',
        'allowed_mimes' => 'Allowed types',
    ],

    'options' => [
        'theme' => [
            'light' => 'Light',
            'dark' => 'Dark',
            'system' => 'Follow system',
        ],
        'sidebar' => [
            'expanded' => 'Expanded',
            'collapsed' => 'Collapsed',
        ],
        'mail_driver' => [
            'log' => 'Write to log (no delivery)',
            'smtp' => 'SMTP',
            'array' => 'No delivery (testing)',
        ],
        'mail_encryption' => [
            'none' => 'None',
        ],
    ],

    'help' => [
        'primary_color' => 'Applied to buttons, links and active elements.',
        'app_name' => 'Shown in the panel header when no logo is set.',
        'timezone' => 'All dates in the panel are displayed in this zone.',
        'mail_host' => 'The server itself connects to this host — use one you trust.',
        'mail_password' => 'Leave blank to keep the current password. It is never shown after saving.',
        'private_disk' => 'Must be a disk that supports temporary URLs (S3, or local with serve).',
        'private_collections' => 'A collection is defined by the model that registers it in code.',
        'max_upload_size_kb' => 'Keep below the PHP limits (`upload_max_filesize`).',
        'allowed_mimes' => 'MIME types such as image/png. SVG is blocked for security reasons.',
    ],

    'locale' => 'Language',
    'text' => 'Text',

    'validation' => [
        'no_temporary_urls' => 'This disk does not support temporary URLs, and private collections must never get permanent links. Choose S3, or a local disk with serve enabled.',
        'forbidden_mime' => ':mime is not allowed: an SVG executes script, and uploading one into a public collection is a stored XSS hole.',
        'low_contrast' => 'This colour has insufficient contrast on a white background (:ratio:1 minimum). Pick a darker colour so buttons and links stay readable.',
    ],

    'saved' => 'Settings saved.',

];
