<?php

declare(strict_types=1);

/**
 * مدة كاش الصلاحيات. (docs/14 بند ٢ · Week 5 Slice 5.10)
 *
 * ⚠️ config('permission.cache.expiration_time') بيتحمّل مرة واحدة وقت
 *    إقلاع التطبيق — تغيير env() جوّه الاختبار بعدها مايأثّرش على القيمة
 *    المحمّلة أصلاً. الاختبار هنا بيعيد تحميل ملف الكونفيج نفسه (require)
 *    بعد ضبط/مسح env، عشان يفحص منطق الملف مباشرة مش قيمة مجمّدة.
 */
function permissionCacheExpirationTimeWithEnv(?string $ttl): DateInterval
{
    if ($ttl === null) {
        putenv('PERMISSION_CACHE_TTL');
    } else {
        putenv("PERMISSION_CACHE_TTL={$ttl}");
    }

    $config = require base_path('config/permission.php');

    putenv('PERMISSION_CACHE_TTL');

    return $config['cache']['expiration_time'];
}

it('القيمة الافتراضية لسه ٢٤ ساعة من غير PERMISSION_CACHE_TTL', function (): void {
    $interval = permissionCacheExpirationTimeWithEnv(null);

    expect($interval)->toBeInstanceOf(DateInterval::class)
        ->and($interval->h)->toBe(24)
        ->and($interval->d)->toBe(0);
});

it('config/permission.php الفعلي بيرجّع نفس الافتراضي بعد الإقلاع', function (): void {
    $interval = config('permission.cache.expiration_time');

    expect($interval)->toBeInstanceOf(DateInterval::class)
        ->and($interval->h)->toBe(24);
});

it('PERMISSION_CACHE_TTL لما تتضبط بتتعكس في الـ DateInterval الناتج', function (): void {
    $interval = permissionCacheExpirationTimeWithEnv('48 hours');

    expect($interval)->toBeInstanceOf(DateInterval::class)
        ->and($interval->h)->toBe(48);
});

it('PERMISSION_CACHE_TTL موثّقة في .env.example بقيمتها الافتراضية', function (): void {
    $envExample = (string) file_get_contents(base_path('.env.example'));

    expect($envExample)->toContain('PERMISSION_CACHE_TTL="24 hours"');
});
