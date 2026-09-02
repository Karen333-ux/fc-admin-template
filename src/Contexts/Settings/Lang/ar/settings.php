<?php

declare(strict_types=1);

return [

    'pages' => [
        'appearance' => 'المظهر',
    ],

    'sections' => [
        'brand' => 'العلامة',
        'brand_help' => 'الألوان والخطوط اللي بتظهر في اللوحة كلها.',
        'layout' => 'الشكل العام',
    ],

    'fields' => [
        'primary_color' => 'اللون الأساسي',
        'font' => 'الخط',
        'default_theme' => 'الوضع الافتراضي',
        'allow_theme_switch' => 'السماح بتبديل الوضع',
        'sidebar_default' => 'القائمة الجانبية',
    ],

    'options' => [
        'theme' => [
            'light' => 'فاتح',
            'dark' => 'داكن',
            'system' => 'حسب النظام',
        ],
        'sidebar' => [
            'expanded' => 'مفتوحة',
            'collapsed' => 'مطويّة',
        ],
    ],

    'help' => [
        'primary_color' => 'بيتطبّق على الأزرار والروابط والعناصر النشطة.',
    ],

    'validation' => [
        'low_contrast' => 'اللون ده تباينه ضعيف على الخلفية البيضا (المطلوب :ratio:1 على الأقل). اختار لون أغمق عشان الأزرار والروابط تفضل مقروءة.',
    ],

    'saved' => 'اتحفظت الإعدادات.',

];
