<?php

declare(strict_types=1);

/**
 * إعداد spatie/laravel-backup. (docs/11 بند ٩)
 */
it('الوجهة s3-private الموجودة أصلاً — مفيش ديسك جديد اتعمل', function (): void {
    expect(config('backup.backup.destination.disks'))->toBe(['s3-private'])
        ->and(config('filesystems.disks.s3-private.visibility'))->toBe('private');
});

it('مراقبة النسخ الاحتياطي على نفس ديسك الوجهة', function (): void {
    expect(config('backup.monitor_backups.0.disks'))->toBe(['s3-private']);
});

it('إيميل تنبيهات النسخ الاحتياطي جاي من BACKUP_ALERT_EMAIL مش قيمة الوثيقة الافتراضية', function (): void {
    // القيمة لازم تكون إيميل صالح دايماً (spatie/laravel-backup بيتحقق
    // منها وقت بناء الـ Config، مش بس وقت الإرسال) — الافتراضي هنا نفس
    // default القيمة في config/mail.php، مش الافتراضي الأصلي بتاع
    // الباكدج ('your@example.com'). (docs/11 بند ٩)
    expect(config('backup.notifications.mail.to'))
        ->not->toBe('your@example.com')
        ->and(filter_var(config('backup.notifications.mail.to'), FILTER_VALIDATE_EMAIL))
        ->not->toBeFalse();

    $envExample = (string) file_get_contents(base_path('.env.example'));

    expect($envExample)->toContain('BACKUP_ALERT_EMAIL=');
});
