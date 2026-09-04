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
        ],

        'settings' => [
            'updated' => 'تعديل الإعدادات',
        ],

        'auth' => [
            'login_failed' => 'محاولة دخول فاشلة',
        ],

    ],

];
