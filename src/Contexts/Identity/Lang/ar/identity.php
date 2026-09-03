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

    // الحالة الفارغة الخاصة بالمستخدمين — الرسالة العامة في lang/*/table.php
    'empty' => [
        'heading' => 'مفيش مستخدمين لسه',
        'description' => 'ابدأ بإضافة أول مستخدم للمؤسسة.',
        'cta' => 'إضافة مستخدم',
    ],

];
