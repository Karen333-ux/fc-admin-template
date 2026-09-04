<?php

declare(strict_types=1);

return [

    'events' => [

        'user' => [
            'created' => 'إنشاء مستخدم',
            'updated' => 'تعديل مستخدم',
            'deleted' => 'حذف مستخدم',
            'restored' => 'استرجاع مستخدم',
            'role_attached' => 'إسناد دور للمستخدم',
            'role_detached' => 'إزالة دور من المستخدم',
            'force_logout' => 'إنهاء باقي الجلسات',
            'device_terminated' => 'إنهاء جلسة جهاز',
        ],

        'settings' => [
            'updated' => 'تعديل الإعدادات',
        ],

        'auth' => [
            'login_failed' => 'محاولة دخول فاشلة',
        ],

    ],

];
