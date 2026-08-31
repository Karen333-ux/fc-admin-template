<?php

declare(strict_types=1);

return [

    'actions' => [
        'view_any' => 'عرض الكل',
        'view' => 'عرض',
        'create' => 'إضافة',
        'update' => 'تعديل',
        'delete' => 'حذف',
        'delete_any' => 'حذف جماعي',
        'restore' => 'استرجاع',
        'force_delete' => 'حذف نهائي',
        'export' => 'تصدير',
        'impersonate' => 'انتحال شخصية',
        'reset_password' => 'إعادة تعيين كلمة المرور',
        'force_logout' => 'إنهاء الجلسات',
    ],

    'resources' => [
        'users' => 'المستخدمين',
    ],

    'groups' => [
        'identity' => 'الهوية والمستخدمين',
        'system' => 'النظام',
    ],

    // الصفحات والودجتس ليها كتالوج منفصل ومفتاحها بيتبحث **كامل**. (ADR-003)
    'pages' => [
        'access.panel.admin' => 'الدخول للوحة التحكم',
        'access.dashboard' => 'عرض لوحة المعلومات',
    ],

    'widgets' => [],

    // رسائل الرفض بتوصل للمستخدم فعلاً — خليها مفيدة. (docs/19 بند ١١)
    'denied' => [
        'record_not_found' => 'السجل ده مش موجود.',
        'missing_permission' => 'مامعاكش صلاحية «:permission».',
        'self_target' => 'مينفعش تعمل العملية دي على حسابك أنت.',
        'record_trashed' => 'السجل ده متحذوف — استرجعه الأول.',
    ],

];
