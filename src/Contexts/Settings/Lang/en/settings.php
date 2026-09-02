<?php

declare(strict_types=1);

return [

    'pages' => [
        'appearance' => 'Appearance',
    ],

    'sections' => [
        'brand' => 'Brand',
        'brand_help' => 'Colours and fonts used across the whole panel.',
        'layout' => 'Layout',
    ],

    'fields' => [
        'primary_color' => 'Primary colour',
        'font' => 'Font',
        'default_theme' => 'Default theme',
        'allow_theme_switch' => 'Allow theme switching',
        'sidebar_default' => 'Sidebar',
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
    ],

    'help' => [
        'primary_color' => 'Applied to buttons, links and active elements.',
    ],

    'validation' => [
        'low_contrast' => 'This colour has insufficient contrast on a white background (:ratio:1 minimum). Pick a darker colour so buttons and links stay readable.',
    ],

    'saved' => 'Settings saved.',

];
