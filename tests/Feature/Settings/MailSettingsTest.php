<?php

declare(strict_types=1);

use App\Providers\DynamicConfigServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Contexts\Settings\Domain\Settings\MailSettings;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;

/** الصف الخام من جدول `settings` — من غير ما يعدّي على فكّ التشفير */
function rawMailSetting(string $name): ?string
{
    $row = DB::table('settings')
        ->where('group', 'mail')
        ->where('name', $name)
        ->value('payload');

    return $row === null ? null : (string) $row;
}

// ────────────────────────────────────────────────────────────────
// التسجيل والافتراضيات
// ────────────────────────────────────────────────────────────────

it('MailSettings متسجّلة وبتتحل من الحاوية', function (): void {
    expect(config('settings.settings'))->toContain(MailSettings::class)
        ->and(app(MailSettings::class))->toBeInstanceOf(MailSettings::class);
});

it('الهجرة بتزرع الافتراضيات الموثّقة', function (): void {
    $mail = app(MailSettings::class);

    // تثبيت جديد مايبعتش بريد حقيقي قبل ما حد يظبط السيرفر
    expect($mail->driver)->toBe('log')
        ->and($mail->port)->toBe(587)
        ->and($mail->encryption)->toBe('tls')
        ->and($mail->password)->toBe('')
        ->and($mail->from_name)->toHaveKeys(['ar', 'en']);
});

// ────────────────────────────────────────────────────────────────
// التشفير — الحد الأمني الأول
// ────────────────────────────────────────────────────────────────

it('كلمة مرور SMTP مش مكتوبة نص صريح في قاعدة البيانات', function (): void {
    // ⚠️ الجوهر: `encrypted()` ادّعاء لحد ما الصف الخام يتقرا. الاختبار ده
    // بيقرا العمود نفسه مش الخاصية اللي الباكدج بيفكّها.
    $secret = 'p@ssw0rd-super-secret-9174';

    $mail = app(MailSettings::class);
    $mail->password = $secret;
    $mail->save();

    $raw = rawMailSetting('password');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain($secret)
        // ومع ذلك بترجع صح بعد فكّ التشفير
        ->and(app(MailSettings::class)->refresh()->password)->toBe($secret);
});

it('كلمة المرور معلنة في encrypted() ومفيش سر تاني منسي', function (): void {
    expect(MailSettings::encrypted())->toContain('password')
        ->and(MailSettings::group())->toBe('mail');
});

it('باقي الحقول مش مشفّرة — التشفير للسر بس', function (): void {
    $mail = app(MailSettings::class);
    $mail->host = 'smtp.example.test';
    $mail->save();

    expect(rawMailSetting('host'))->toContain('smtp.example.test');
});

// ────────────────────────────────────────────────────────────────
// الإقلاع على قاعدة بيانات نضيفة — الحارس
// ────────────────────────────────────────────────────────────────

it('المزوّد مابيكسرش الإقلاع لما جدول settings مايكونش موجود', function (): void {
    // ⚠️ الحالة دي حقيقية: أول `migrate` على قاعدة فاضية. من غير الحارس
    // المزوّد بيكسر **كل** أوامر artisan — بما فيها migrate نفسه.
    Schema::drop('settings');

    $provider = new DynamicConfigServiceProvider(app());

    expect($provider->settingsTableExists())->toBeFalse();

    // مايرميش، ومايلمسش الكونفيج
    $before = config('mail.default');
    $provider->boot();

    expect(config('mail.default'))->toBe($before);
});

it('المزوّد بيعدّي بهدوء لو الجدول موجود ومجموعة mail لسه ماتهاجرتش', function (): void {
    // بين هجرتين: الجدول موجود بس المجموعة لسه فاضية
    DB::table('settings')->where('group', 'mail')->delete();

    $before = config('mail.from.address');

    (new DynamicConfigServiceProvider(app()))->boot();

    expect(config('mail.from.address'))->toBe($before);
});

// ────────────────────────────────────────────────────────────────
// الحقن وقت التشغيل
// ────────────────────────────────────────────────────────────────

it('كونفيج البريد بيعكس الإعدادات المحفوظة من غير ديبلوي', function (): void {
    $mail = app(MailSettings::class);
    $mail->driver = 'smtp';
    $mail->host = 'smtp.mailer.test';
    $mail->port = 2525;
    $mail->username = 'postmaster';
    $mail->password = 'secret-runtime';
    $mail->encryption = 'ssl';
    $mail->from_address = 'noreply@mailer.test';
    $mail->from_name = ['ar' => 'المرسل', 'en' => 'Sender'];
    $mail->save();

    app()->setLocale('en');
    (new DynamicConfigServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.mailer.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.username'))->toBe('postmaster')
        ->and(config('mail.mailers.smtp.password'))->toBe('secret-runtime')
        ->and(config('mail.mailers.smtp.encryption'))->toBe('ssl')
        ->and(config('mail.from.address'))->toBe('noreply@mailer.test')
        ->and(config('mail.from.name'))->toBe('Sender');
});

it('تشفير فاضي بيتحوّل لـ null مش نص فاضي', function (): void {
    // Laravel بيتوقّع null لما مفيش تشفير — '' بيكسر الاتصال
    $mail = app(MailSettings::class);
    $mail->encryption = '';
    $mail->save();

    (new DynamicConfigServiceProvider(app()))->boot();

    expect(config('mail.mailers.smtp.encryption'))->toBeNull();
});

it('اسم المُرسِل بيتبع اللغة ويرجع للاحتياطية', function (): void {
    $mail = app(MailSettings::class);
    $mail->from_name = ['ar' => 'كود المستقبل', 'en' => 'Future Code'];
    $mail->save();

    app()->setLocale('ar');
    (new DynamicConfigServiceProvider(app()))->boot();
    expect(config('mail.from.name'))->toBe('كود المستقبل');

    app()->setLocale('fr');
    (new DynamicConfigServiceProvider(app()))->boot();
    expect(config('mail.from.name'))->toBe('Future Code');
});

// ────────────────────────────────────────────────────────────────
// عامة مش لكل مستأجر
// ────────────────────────────────────────────────────────────────

it('إعدادات البريد واحدة لكل المستأجرين', function (): void {
    // مستأجر يقدر يغيّر سيرفر البريد = يقدر يوجّه رسايل التثبيت كله لسيرفره
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $mail = app(MailSettings::class);
    $mail->host = 'global.smtp.test';
    $mail->save();

    actingWithinTenant($a);
    $fromA = app(MailSettings::class)->refresh()->host;

    actingWithinTenant($b);
    $fromB = app(MailSettings::class)->refresh()->host;

    expect($fromA)->toBe($fromB)->toBe('global.smtp.test');
});

it('حفظ إعدادات البريد مابيكتبش صف في tenant_settings', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    $mail = app(MailSettings::class);
    $mail->host = 'tenant.smtp.test';
    $mail->save();

    expect(DB::table('tenant_settings')->where('group', 'mail')->count())->toBe(0);
});

it('إعدادات البريد بتتقرا من غير سياق مستأجر', function (): void {
    // الطوابير والأوامر بتبعت بريد من غير سياق مستأجر
    app(TenantContext::class)->set(null);

    expect(app(MailSettings::class)->driver)->toBeString()->not->toBeEmpty();
});
