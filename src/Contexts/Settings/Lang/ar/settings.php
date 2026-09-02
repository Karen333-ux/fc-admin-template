<?php

declare(strict_types=1);

return [

    'pages' => [
        'appearance' => 'المظهر',
        'general' => 'الإعدادات العامة',
        'mail' => 'البريد',
        'storage' => 'التخزين',
    ],

    'sections' => [
        'brand' => 'العلامة',
        'brand_help' => 'الألوان والخطوط اللي بتظهر في اللوحة كلها.',
        'layout' => 'الشكل العام',
        'identity' => 'هوية التطبيق',
        'identity_help' => 'الاسم والوصف اللي بيظهروا في اللوحة والرسايل.',
        'contact' => 'بيانات الدعم',
        'localization' => 'اللغة والوقت',
        'maintenance' => 'وضع الصيانة',
        'maintenance_help' => 'لمّا يتفعّل، الرسالة دي بتظهر بدل المحتوى.',
        'mail_transport' => 'سيرفر الإرسال',
        'mail_transport_help' => 'التغيير هنا بيأثّر على الرسالة الجاية من غير إعادة نشر.',
        'mail_sender' => 'بيانات المُرسِل',
        'storage_disks' => 'الديسكات',
        'storage_disks_help' => 'الاختيارات جاية من `config/filesystems.php` — مفيش أسماء مكتوبة في الكود.',
        'storage_collections' => 'المجموعات الخاصة',
        'storage_collections_help' => 'ملفات المجموعات دي بتروح لديسك الخاص وروابطها مؤقتة بس.',
        'storage_uploads' => 'حدود الرفع',
    ],

    'fields' => [
        'primary_color' => 'اللون الأساسي',
        'font' => 'الخط',
        'default_theme' => 'الوضع الافتراضي',
        'allow_theme_switch' => 'السماح بتبديل الوضع',
        'sidebar_default' => 'القائمة الجانبية',
        'app_name' => 'اسم التطبيق',
        'app_description' => 'وصف التطبيق',
        'support_email' => 'بريد الدعم',
        'support_phone' => 'تليفون الدعم',
        'default_locale' => 'اللغة الافتراضية',
        'timezone' => 'المنطقة الزمنية',
        'maintenance_mode' => 'تفعيل وضع الصيانة',
        'maintenance_message' => 'رسالة الصيانة',
        'mail_driver' => 'طريقة الإرسال',
        'mail_host' => 'السيرفر',
        'mail_port' => 'البورت',
        'mail_username' => 'اسم المستخدم',
        'mail_password' => 'كلمة المرور',
        'mail_encryption' => 'التشفير',
        'mail_from_address' => 'البريد المُرسِل منه',
        'mail_from_name' => 'اسم المُرسِل',
        'default_disk' => 'الديسك الافتراضي',
        'private_disk' => 'ديسك الملفات الخاصة',
        'private_collections' => 'المجموعات الخاصة',
        'max_upload_size_kb' => 'أقصى حجم للملف (كيلوبايت)',
        'allowed_mimes' => 'الأنواع المسموحة',
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
        'mail_driver' => [
            'log' => 'تسجيل في اللوج (من غير إرسال)',
            'smtp' => 'SMTP',
            'array' => 'من غير إرسال (للاختبار)',
        ],
        'mail_encryption' => [
            'none' => 'من غير تشفير',
        ],
    ],

    'help' => [
        'primary_color' => 'بيتطبّق على الأزرار والروابط والعناصر النشطة.',
        'app_name' => 'بيظهر في رأس اللوحة لو مفيش لوجو.',
        'timezone' => 'كل التواريخ في اللوحة بتتعرض بيها.',
        'mail_host' => 'السيرفر ده بيتوصل بيه من الخادم نفسه — حطّ سيرفر بتثق فيه.',
        'mail_password' => 'سيبها فاضية عشان تفضل زي ما هي. مابتتعرضش بعد الحفظ.',
        'private_disk' => 'لازم يكون ديسك بيدعم الروابط المؤقتة (S3 أو local بـ serve).',
        'private_collections' => 'المجموعة بيعرّفها الموديل اللي بيسجّلها في الكود.',
        'max_upload_size_kb' => 'لازم يفضل أقل من حدود PHP (`upload_max_filesize`).',
        'allowed_mimes' => 'أنواع MIME زي image/png. SVG ممنوع لأسباب أمنية.',
    ],

    'locale' => 'اللغة',
    'text' => 'النص',

    'validation' => [
        'no_temporary_urls' => 'الديسك ده مابيدعمش الروابط المؤقتة، والمجموعات الخاصة ماينفعش يبقى لها روابط دائمة. اختار S3 أو ديسك local مفعّل عليه serve.',
        'forbidden_mime' => 'النوع :mime ممنوع: ملف SVG بينفّذ سكربت، ورفعه في مجموعة عامة ثغرة XSS مخزّنة.',
        'low_contrast' => 'اللون ده تباينه ضعيف على الخلفية البيضا (المطلوب :ratio:1 على الأقل). اختار لون أغمق عشان الأزرار والروابط تفضل مقروءة.',
    ],

    'saved' => 'اتحفظت الإعدادات.',

];
