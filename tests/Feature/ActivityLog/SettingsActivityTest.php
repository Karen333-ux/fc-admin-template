<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Src\Contexts\Settings\Domain\Settings\GeneralSettings;
use Src\Contexts\Settings\Domain\Settings\MailSettings;
use Src\Contexts\Settings\Presentation\Filament\Pages\ManageGeneral;
use Src\Contexts\Settings\Presentation\Filament\Pages\ManageMail;
use Src\Support\Domain\Models\Tenant;

/**
 * تسجيل تعديل الإعدادات — خطاف `afterSave()` صريح/يدوي، القرار المعتمد.
 * الإعدادات مش موديل Eloquent فـ `LogsActivity` ما بتتحطش عليها مباشرة.
 * (docs/11 بند ٥)
 */
function settingsContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

it('حفظ صفحة الإعدادات العامة بيسجّل نشاط updated تحت security', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    settingsContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageGeneral::class)
        ->fillForm([
            'app_name' => ['ar' => 'اسم جديد', 'en' => 'New name'],
            'app_description' => ['ar' => '', 'en' => ''],
            'support_email' => 'new@example.test',
            'support_phone' => '+20100000000',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'maintenance_mode' => false,
            'maintenance_message' => ['ar' => '', 'en' => ''],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $activity = Activity::query()
        ->where('log_name', 'security')
        ->where('properties->settings', GeneralSettings::class)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe(__('audit.events.settings.updated'))
        ->and($activity->properties['new']['support_email'] ?? null)->toBe('new@example.test');
});

it('حفظ من غير تغيير فعلي مايسجّلش نشاط', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    settingsContext($tenant, $admin);

    // بنثبّت قيمة حقيقية الأول (support_phone فاضي افتراضياً — فورم
    // Filament بيحوّل الفاضي لـ null، وGeneralSettings::$support_phone
    // مش nullable. مش خلل في الشريحة دي — بس بيتفادى هنا).
    $form = [
        'app_name' => ['ar' => 'ثابت', 'en' => 'Stable'],
        'app_description' => ['ar' => 'وصف', 'en' => 'Description'],
        'support_email' => 'stable@example.test',
        'support_phone' => '+20100000000',
        'default_locale' => 'ar',
        'timezone' => 'UTC',
        'maintenance_mode' => false,
        'maintenance_message' => ['ar' => 'صيانة', 'en' => 'Maintenance'],
    ];

    Livewire::actingAs($admin)->test(ManageGeneral::class)->fillForm($form)->call('save')
        ->assertHasNoFormErrors();

    $before = Activity::query()->where('properties->settings', GeneralSettings::class)->count();

    Livewire::actingAs($admin)->test(ManageGeneral::class)->fillForm($form)->call('save')
        ->assertHasNoFormErrors();

    expect(Activity::query()->where('properties->settings', GeneralSettings::class)->count())->toBe($before);
});

it('كلمة مرور البريد بتتنقّى من سجل النشاط', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    settingsContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageMail::class)
        ->fillForm([
            'driver' => 'smtp',
            'encryption' => 'tls',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'mailer',
            'password' => 'super-secret-mail-password',
            'from_address' => 'noreply@example.test',
            'from_name' => ['ar' => 'كود المستقبل', 'en' => 'Future Code'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $activity = Activity::query()
        ->where('properties->settings', MailSettings::class)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();

    $raw = json_encode($activity->properties->toArray());

    expect($raw)->not->toContain('super-secret-mail-password')
        ->and($activity->properties['new']['password'] ?? null)->toBe('[REDACTED]');
});
