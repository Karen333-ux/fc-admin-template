<?php

declare(strict_types=1);

return [

    'resource' => [
        'singular' => 'مستخدم',
        'plural' => 'المستخدمين',
    ],

    'fields' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'created_at' => 'تاريخ الإضافة',
        'roles' => 'الأدوار',
    ],

    'actions' => [
        'activity_log' => 'سجل النشاط',
        'impersonate' => 'انتحال شخصية',
    ],

    'pages' => [
        'my_devices' => 'أجهزتي',
    ],

    'devices' => [
        'fields' => [
            'device' => 'الجهاز',
            'last_active_at' => 'آخر نشاط',
            'status' => 'الحالة',
        ],
        'unknown_device' => 'جهاز مش معروف',
        'current_device' => 'الجهاز ده',
        'actions' => [
            'terminate' => 'إنهاء',
            'terminate_others' => 'إنهاء كل الجلسات الأخرى',
        ],
        'notifications' => [
            'terminated' => 'اتقفلت جلسة الجهاز.',
            'others_terminated' => 'اتقفلت كل الجلسات التانية.',
        ],
        'empty' => [
            'heading' => 'مفيش أجهزة تانية',
        ],
    ],

    // الحالة الفارغة الخاصة بالمستخدمين — الرسالة العامة في lang/*/table.php
    'empty' => [
        'heading' => 'مفيش مستخدمين لسه',
        'description' => 'ابدأ بإضافة أول مستخدم للمؤسسة.',
        'cta' => 'إضافة مستخدم',
    ],

    'notifications' => [
        'invited' => [
            'subject' => 'اتدعيت لمؤسسة جديدة',
            'greeting' => 'أهلاً بيك،',
            'body' => 'اتدعيت تنضم لمؤسسة على لوحة كود المستقبل. اضغط الزرار عشان تكمّل تسجيلك.',
            'short' => 'في دعوة مستنياك للانضمام لمؤسسة.',
            'cta' => 'اقبل الدعوة',
        ],
        'new_device' => [
            'subject' => 'دخول جديد لحسابك',
            'greeting' => 'أهلاً بيك،',
            'body' => 'حصل دخول لحسابك من جهاز جديد: :device، عنوان IP :ip، في :time.',
            'warning' => 'لو ده مش أنت، غيّر كلمة المرور فوراً وفعّل المصادقة الثنائية.',
            'unknown_device' => 'جهاز مش معروف',
        ],
    ],

];
