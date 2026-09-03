<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Notifications\UserInvitedNotification;
use Src\Contexts\Settings\Domain\Settings\GeneralSettings;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Notifications\NotificationOwnership;
use Src\Support\Presentation\Filament\Notifications\TenantAwareDatabaseNotifications;

function invited(): UserInvitedNotification
{
    return new UserInvitedNotification('https://example.test/invite/abc');
}

function memberOf(Tenant $tenant, ?string $locale = null): User
{
    $user = User::factory()->create(['locale' => $locale]);
    $user->tenants()->attach($tenant);

    return $user;
}

// ────────────────────────────────────────────────────────────────
// عقد الطابور — docs/09 بند ٢ قواعد ١ و ٢
// ────────────────────────────────────────────────────────────────

it('الإشعار في الطابور ومابيتبعتش متزامن', function (): void {
    expect(invited())->toBeInstanceOf(ShouldQueue::class);
});

it('afterCommit مفعّلة — مايتبعتش قبل ما الترانزاكشن تخلص', function (): void {
    // ⚠️ من غيرها الطابور بياخد الـ Job قبل ما السجل يتحفظ، فالإشعار
    //    بيتكلم عن حاجة لسه مش موجودة.
    expect(invited()->afterCommit)->toBeTrue();
});

it('على طابور الإشعارات المنفصل', function (): void {
    expect(invited()->queue)->toBe(config('notifications.queue'));
});

it('محاولات وتأخير محددين صراحةً', function (): void {
    $notification = invited();

    expect($notification->tries)->toBe(5)
        ->and($notification->backoff)->not->toBeEmpty();
});

it('المفتاح موجود في الكتالوج', function (): void {
    // معيار قبول docs/09 بند ٨: اختبار بيفشل لو إشعار مش في الكتالوج
    expect(config('notifications.catalog.'.UserInvitedNotification::key()))->not->toBeNull();
});

// ────────────────────────────────────────────────────────────────
// محتوى الرسائل
// ────────────────────────────────────────────────────────────────

it('toMail بيبني رسالة فيها الرابط والنصوص مترجمة', function (): void {
    $user = User::factory()->create();
    $mail = invited()->toMail($user);

    expect($mail->actionUrl)->toBe('https://example.test/invite/abc')
        ->and($mail->subject)->toBe(__('identity::identity.notifications.invited.subject'))
        ->and($mail->subject)->not->toContain('identity::');
});

it('toDatabase بيرجّع رسالة Filament مختومة بالمستأجر', function (): void {
    $tenant = Tenant::factory()->create();
    $user = memberOf($tenant);

    actingWithinTenant($tenant);

    $data = invited()->toDatabase($user);

    expect($data)->toHaveKey('format')
        ->and($data['format'])->toBe('filament')
        ->and($data)->toHaveKey(NotificationOwnership::TENANT_KEY)
        ->and($data[NotificationOwnership::TENANT_KEY])->toBe($tenant->getKey());
});

it('المستأجر بيتلقط وقت الإنشاء مش وقت تنفيذ الـ Job', function (): void {
    // ⚠️⚠️ باگ حقيقي اتمسك هنا: كل إشعار `ShouldQueue`، و
    //     `AppServiceProvider::forgetTenantContextBetweenJobs()` بيمسح
    //     `TenantContext` في `Queue::before`. يعني `toDatabase()` بتشتغل
    //     **من غير سياق** دايماً.
    //
    //     أول نسخة كانت بتقرا المستأجر جوّه `toDatabase()` فكانت بتختم
    //     `null` — والجرس كان بيفتح على كل المستأجرين. الاختبار ده بيثبت
    //     إن القيمة اتلقطت في الكونستركتور.
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    $notification = invited();

    // نحاكي اللي بيحصل قبل كل Job
    app(TenantContext::class)->forget();
    app(TenantContext::class)->set(null);

    $user = memberOf($tenant);

    expect($notification->tenantId)->toBe($tenant->getKey())
        ->and($notification->toDatabase($user)[NotificationOwnership::TENANT_KEY])
        ->toBe($tenant->getKey());
});

it('من غير سياق مستأجر الإشعار بيتختم بـ null', function (): void {
    // إشعار على مستوى المستخدم — بيوصل في أي سياق
    $user = User::factory()->create();

    expect(invited()->toDatabase($user)[NotificationOwnership::TENANT_KEY])->toBeNull();
});

// ────────────────────────────────────────────────────────────────
// لغة المستقبِل — docs/09 بند ٤
// ────────────────────────────────────────────────────────────────

it('المستخدم بيعلن تفضيل لغة', function (): void {
    expect(User::factory()->create())->toBeInstanceOf(HasLocalePreference::class);
});

it('لغة المستقبِل بتغلب لغة التطبيق', function (): void {
    // ⚠️ معيار القبول: اللي بعت شغّال بالعربي، والمستقبِل لغته en
    $tenant = Tenant::factory()->create();
    $english = memberOf($tenant, 'en');

    app()->setLocale('ar');

    Notification::fake();
    $english->notify(invited());

    Notification::assertSentTo($english, UserInvitedNotification::class);

    // الرسالة نفسها بتتبني بلغة المستقبِل
    app()->setLocale($english->preferredLocale());
    expect(invited()->toMail($english)->subject)
        ->toBe(trans('identity::identity.notifications.invited.subject', [], 'en'));
});

it('مستخدم لغته ar بياخد العربي', function (): void {
    $arabic = User::factory()->create(['locale' => 'ar']);

    expect($arabic->preferredLocale())->toBe('ar');
});

it('لغة فاضية بترجع للإعداد العام', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->default_locale = 'en';
    $settings->save();

    $user = User::factory()->create(['locale' => null]);

    expect($user->preferredLocale())->toBe('en');
});

it('تغيير الإعداد العام بيغيّر الاحتياطي', function (): void {
    // بيثبت إن المصدر هو GeneralSettings مش قيمة مثبّتة
    $settings = app(GeneralSettings::class);
    $settings->default_locale = 'ar';
    $settings->save();

    expect(User::factory()->create(['locale' => null])->preferredLocale())->toBe('ar');
});

// ────────────────────────────────────────────────────────────────
// عزل المستأجرين على الجرس — ADR-024
// ────────────────────────────────────────────────────────────────

it('جرس مستأجر ب مابيعرضش إشعار مستأجر أ', function (): void {
    // ⚠️⚠️ الحد الأمني للشريحة. جدول notifications مافيهوش tenant_id،
    //     فالفلترة على data->tenant_id هي الطبقة الوحيدة.
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $user = User::factory()->create();
    $user->tenants()->attach([$a->getKey(), $b->getKey()]);

    actingWithinTenant($a);
    $user->notify(invited());

    actingWithinTenant($b);
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($b);

    $visible = Livewire::actingAs($user)
        ->test(TenantAwareDatabaseNotifications::class)
        ->instance()
        ->getNotificationsQuery()
        ->count();

    expect($visible)->toBe(0);
});

it('جرس نفس المستأجر بيعرض الإشعار', function (): void {
    $tenant = Tenant::factory()->create();
    $user = memberOf($tenant);

    actingWithinTenant($tenant);
    $user->notify(invited());

    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);

    $visible = Livewire::actingAs($user)
        ->test(TenantAwareDatabaseNotifications::class)
        ->instance()
        ->getNotificationsQuery()
        ->count();

    expect($visible)->toBe(1);
});

it('إشعار على مستوى المستخدم بيظهر في أي مستأجر', function (): void {
    // اللي مالوش مستأجر بيخص الحساب نفسه — لازم يوصل في أي سياق
    $tenant = Tenant::factory()->create();
    $user = memberOf($tenant);

    app(TenantContext::class)->set(null);
    $user->notify(invited());

    actingWithinTenant($tenant);
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);

    $visible = Livewire::actingAs($user)
        ->test(TenantAwareDatabaseNotifications::class)
        ->instance()
        ->getNotificationsQuery()
        ->count();

    expect($visible)->toBe(1);
});

it('الإشعار بيتكتب فعلاً في جدول notifications', function (): void {
    $tenant = Tenant::factory()->create();
    $user = memberOf($tenant);

    actingWithinTenant($tenant);
    $user->notify(invited());

    expect(DB::table('notifications')->where('notifiable_id', $user->getKey())->count())->toBe(1);
});

// ────────────────────────────────────────────────────────────────
// تسجيل اللوحة
// ────────────────────────────────────────────────────────────────

it('اللوحة بتستخدم جرس مقيّد بالمستأجر', function (): void {
    Filament::setCurrentPanel('admin');
    $panel = Filament::getCurrentPanel();

    expect($panel->hasDatabaseNotifications())->toBeTrue()
        ->and($panel->getDatabaseNotificationsLivewireComponent())
        ->toBe(TenantAwareDatabaseNotifications::class)
        ->and($panel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});
