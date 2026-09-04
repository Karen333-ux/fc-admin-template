<?php

declare(strict_types=1);

// إضافة أحداث مخصّصة فوق كتالوج الحزمة الافتراضي (created/updated/deleted/restored)
// عبر lang/vendor/{package}/{locale} — بيتدمج مع ملف الحزمة، مش بيستبدله.
// (docs/11 بند ٩ · Slice 4.3)
return [
    'events' => [
        'role_attached' => 'Role assigned',
        'role_detached' => 'Role removed',
        'login_failed' => 'Failed login attempt',
    ],
];
