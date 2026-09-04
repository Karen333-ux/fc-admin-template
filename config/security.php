<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | فرض المصادقة الثنائية (2FA)
    |--------------------------------------------------------------------------
    | الأدوار المذكورة هنا لازم تفعّل 2FA بعد ما فترة السماح تخلص، وإلا
    | `RequireTwoFactorAuthentication` بيوجّههم لصفحة الإعداد. (docs/12 بند ١)
    |
    | ⚠️ الفحص هنا **Gate على قدرة `require.two_factor`** مش فحص دور مباشر
    |    على المستخدم الفاعل — CLAUDE.md بيمنع فحص الدور المباشر على
    |    `$user` الفاعل بره الـ Policy. القدرة دي متسجّلة في
    |    `config/authorization.php` وممنوحة للأدوار المطلوبة هنا فعلياً —
    |    الملف ده بس بيوثّق القصد ومصدر مدة السماح.
    */
    'two_factor' => [
        'grace_period_days' => (int) env('SECURITY_TWO_FACTOR_GRACE_PERIOD_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | مدة الانتحال القصوى
    |--------------------------------------------------------------------------
    | جلسة الانتحال بتنتهي تلقائياً بعد المدة دي — مش قابلة للتمديد من
    | الواجهة، ومفروضة من السيرفر عبر `EnforceImpersonationTimeout`.
    | (docs/12 بند ٣ قاعدة ٥)
    */
    'impersonation' => [
        'max_minutes' => (int) env('SECURITY_IMPERSONATION_MAX_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | سياسة كلمات المرور
    |--------------------------------------------------------------------------
    | `min_length`/`uncompromised` بيتقروا من `Password::defaults()` في
    | `AppServiceProvider::boot()`. `history_count` بيتقرا من
    | `PasswordHistoryValidation` و`UserPasswordHistoryObserver`. (docs/12 بند ٤)
    |
    | ⚠️ `expires_after_days` **اختياري ومعطّل افتراضياً** بالنص الحرفي في
    |    docs/12 بند ٤ — والبند مالوش أي كود إنفاذ في الوثيقة (على عكس كل
    |    بند تاني في الملف ده). القيمة هنا محجوزة بس، مفيش middleware ولا
    |    شاشة «كلمة مرورك خلصت» — بناء ميزة كاملة من غير مواصفة حقيقية
    |    مخالف لتعليمات النطاق. `null` = معطّلة.
    */
    'password' => [
        'min_length' => (int) env('SECURITY_PASSWORD_MIN_LENGTH', 12),
        'history_count' => (int) env('SECURITY_PASSWORD_HISTORY_COUNT', 5),
        'expires_after_days' => filled(env('SECURITY_PASSWORD_EXPIRES_AFTER_DAYS'))
            ? (int) env('SECURITY_PASSWORD_EXPIRES_AFTER_DAYS')
            : null,
    ],

];
