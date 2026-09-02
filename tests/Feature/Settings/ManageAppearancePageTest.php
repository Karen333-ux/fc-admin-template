<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Src\Contexts\Settings\Domain\Settings\AppearanceSettings;
use Src\Contexts\Settings\Presentation\Filament\Pages\ManageAppearance;
use Src\Support\Domain\Models\Tenant;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function appearanceContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

it('admin بيفتح صفحة المظهر', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    appearanceContext($tenant, $admin);

    expect(ManageAppearance::canAccess())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ManageAppearance::class)
        ->assertOk();
});

it('يمنع من لا يملك manage_appearance من فتح الصفحة', function (string $role): void {
    // الإخفاء تجربة استخدام — canAccess() هي البوابة الفعلية (CLAUDE.md بند ٣)
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    appearanceContext($tenant, $user);

    expect(ManageAppearance::canAccess())->toBeFalse();
})->with(['editor', 'viewer']);

it('يمنع الوصول بالـ URL المباشر لمن لا يملك الصلاحية', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    $this->actingAs($viewer)
        ->get(ManageAppearance::getUrl(tenant: $tenant))
        ->assertForbidden();
});

it('حفظ الصفحة بيغيّر الإعداد العام', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    appearanceContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageAppearance::class)
        ->fillForm([
            'primary_color' => '#123456',
            'font_family' => 'Cairo',
            'default_theme' => 'dark',
            'sidebar_default' => 'collapsed',
            'allow_theme_switch' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(AppearanceSettings::class)->refresh();

    expect($settings->primary_color)->toBe('#123456')
        ->and($settings->font_family)->toBe('Cairo')
        ->and($settings->default_theme)->toBe('dark')
        ->and($settings->allow_theme_switch)->toBeFalse();
});
