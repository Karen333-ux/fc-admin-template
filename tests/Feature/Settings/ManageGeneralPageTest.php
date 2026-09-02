<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Src\Contexts\Settings\Domain\Settings\GeneralSettings;
use Src\Contexts\Settings\Presentation\Filament\Pages\ManageGeneral;
use Src\Support\Domain\Models\Tenant;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function generalContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

dataset('general_matrix', [
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('مصفوفة أدوار manage_general.settings', function (string $role, bool $allowed): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('manage_general.settings'))
        ->toBe($allowed, "الدور {$role} على manage_general.settings");
})->with('general_matrix');

it('admin بيفتح صفحة الإعدادات العامة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    generalContext($tenant, $admin);

    expect(ManageGeneral::canAccess())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ManageGeneral::class)
        ->assertOk();
});

it('يمنع من لا يملك manage_general من فتح الصفحة', function (string $role): void {
    // الإخفاء تجربة استخدام — canAccess() هي البوابة الفعلية (CLAUDE.md بند ٣)
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    generalContext($tenant, $user);

    expect(ManageGeneral::canAccess())->toBeFalse();
})->with(['editor', 'viewer']);

it('يمنع الوصول بالـ URL المباشر لمن لا يملك الصلاحية', function (): void {
    // ⚠️ الجوهر: إخفاء اللينك مابيقفلش الـ endpoint. الاختبار ده بيضرب
    // الـ URL مباشرةً من غير ما يعدّي على القايمة أصلاً.
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    $this->actingAs($viewer)
        ->get(ManageGeneral::getUrl(tenant: $tenant))
        ->assertForbidden();
});

it('الرفض بيعدّي على Response برسالة مترجمة مش bool', function (): void {
    // ADR-019 — من غير كده المستخدم بيتمنع من غير ما يعرف ليه
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    actingWithinTenant($tenant);

    $response = app(GateContract::class)
        ->forUser($viewer)
        ->inspect('manage_general.settings');

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->not->toBeEmpty()
        // مفتاح ترجمة خام معناه إن الرسالة مترجمتش
        ->and($response->message())->not->toContain('authorization.');
});

it('عناوين الصفحة مترجمة في اللغتين', function (): void {
    expect(trans('settings::settings.pages.general', [], 'ar'))
        ->not->toBe('settings::settings.pages.general')
        ->and(trans('settings::settings.pages.general', [], 'en'))
        ->not->toBe(trans('settings::settings.pages.general', [], 'ar'));
});

it('حفظ الصفحة بيغيّر الإعداد العام', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    generalContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageGeneral::class)
        ->fillForm([
            'app_name' => ['ar' => 'اسم جديد', 'en' => 'New name'],
            'app_description' => ['ar' => 'وصف', 'en' => 'Description'],
            'support_email' => 'help@example.test',
            'support_phone' => '+20100000000',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'maintenance_mode' => true,
            'maintenance_message' => ['ar' => 'صيانة', 'en' => 'Maintenance'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(GeneralSettings::class)->refresh();

    expect($settings->app_name['en'])->toBe('New name')
        ->and($settings->support_email)->toBe('help@example.test')
        ->and($settings->default_locale)->toBe('en')
        ->and($settings->timezone)->toBe('UTC')
        ->and($settings->maintenance_mode)->toBeTrue();
});

it('بريد دعم غير صالح بيترفض', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    generalContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageGeneral::class)
        ->fillForm(['support_email' => 'مش-بريد'])
        ->call('save')
        ->assertHasFormErrors(['support_email']);
});
