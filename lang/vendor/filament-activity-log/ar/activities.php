<?php

declare(strict_types=1);

// إضافة أحداث مخصّصة فوق كتالوج الحزمة الافتراضي — بيتدمج معاه مش بيستبدله.
// (docs/11 بند ٩ · Slice 4.3)
return [
    'events' => [
        'role_attached' => 'إسناد دور',
        'role_detached' => 'إزالة دور',
        'login_failed' => 'محاولة دخول فاشلة',
    ],
];
