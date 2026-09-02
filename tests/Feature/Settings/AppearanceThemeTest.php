<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Src\Contexts\Settings\Application\AppearanceResolver;
use Src\Contexts\Settings\Application\TenantSettings;
use Src\Contexts\Settings\Presentation\Filament\Pages\ManageAppearance;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Theming\BrandPalette;

function themeContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

// ────────────────────────────────────────────────────────────────
// اللوحة بتقرا من الإعدادات — مش من قيمة مكتوبة
// ────────────────────────────────────────────────────────────────

it('البانل بياخد لون العلامة من الإعدادات', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    app(TenantSettings::class)->set('appearance', 'primary_color', '#7B1FA2');

    $colors = Filament::getPanel('admin')->getColors();
    $expected = app(BrandPalette::class)->scaleFor('#7B1FA2');

    expect($colors)->toHaveKey('primary')
        ->and($colors['primary'])->toBe($expected);
});

it('لون مستأجر مابيتسربش لمستأجر تاني', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $admin = userWithRole('admin', $a);

    themeContext($a, $admin);
    app(TenantSettings::class)->set('appearance', 'primary_color', '#7B1FA2');
    $colorsA = Filament::getPanel('admin')->getColors()['primary'];

    // نفس اللوحة، مستأجر تاني — لازم ترجع للقيمة العامة
    actingWithinTenant($b);
    $colorsB = Filament::getPanel('admin')->getColors()['primary'];

    $globalScale = app(BrandPalette::class)->scaleFor(config('theme.fallback_brand_color'));

    expect($colorsA)->not->toBe($colorsB)
        ->and($colorsB)->toBe($globalScale);
});

it('الخط بييجي من الإعدادات', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    app(TenantSettings::class)->set('appearance', 'font_family', 'Cairo');

    expect(app(AppearanceResolver::class)->fontFamily())->toBe('Cairo');
});

it('الوضع الداكن بيتبع allow_theme_switch', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    app(TenantSettings::class)->set('appearance', 'allow_theme_switch', false);
    expect(app(AppearanceResolver::class)->allowsThemeSwitch())->toBeFalse();

    app(TenantSettings::class)->set('appearance', 'allow_theme_switch', true);
    expect(app(AppearanceResolver::class)->allowsThemeSwitch())->toBeTrue();
});

// ────────────────────────────────────────────────────────────────
// احتياطي آمن — اللوجو والأيقونة لسه null (الوسائط بره الشريحة)
// ────────────────────────────────────────────────────────────────

it('اللوجو والأيقونة null والاحتياطي آمن', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    $resolver = app(AppearanceResolver::class);

    expect($resolver->logoPath())->toBeNull()
        ->and($resolver->darkLogoPath())->toBeNull()
        ->and($resolver->faviconPath())->toBeNull();

    $panel = Filament::getPanel('admin');

    expect($panel->getBrandLogo())->toBeNull()
        ->and($panel->getFavicon())->toBeNull();
});

it('اللوحة بتفتح عادي واللوجو مفيش', function (): void {
    // الاحتياطي الحقيقي: الصفحة بترندر من غير ما تكسر
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageAppearance::class)
        ->assertOk();
});

// ────────────────────────────────────────────────────────────────
// الفوتر
// ────────────────────────────────────────────────────────────────

it('الفوتر بياخد النص من الإعدادات باللغة الحالية', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    app(TenantSettings::class)->set('appearance', 'footer_text', [
        'ar' => 'حقوق المستأجر',
        'en' => 'Tenant rights',
    ]);

    $resolver = app(AppearanceResolver::class);

    expect($resolver->footerText('ar', 'en'))->toBe('حقوق المستأجر')
        ->and($resolver->footerText('en', 'en'))->toBe('Tenant rights')
        // لغة مش موجودة → بترجع للاحتياطية
        ->and($resolver->footerText('fr', 'en'))->toBe('Tenant rights');
});

it('الفوتر بيرجع نص فاضي لو الإعداد مش مصفوفة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    app(TenantSettings::class)->set('appearance', 'footer_text', 'not-an-array');

    expect(app(AppearanceResolver::class)->footerText('ar', 'en'))->toBe('');
});

// ────────────────────────────────────────────────────────────────
// التباين — معيار قبول docs/06 بند ٢
// ────────────────────────────────────────────────────────────────

it('لون العلامة الافتراضي بيعدّي تباين ٤.٥:١', function (): void {
    expect(app(BrandPalette::class)->meetsTextContrast(config('theme.fallback_brand_color')))
        ->toBeTrue();
});

it('بيرفض لون فاتح تباينه ضعيف', function (string $hex): void {
    expect(app(BrandPalette::class)->meetsTextContrast($hex))->toBeFalse();
})->with(['#FFFF00', '#4ADE80', '#FBBF24', '#EEEEEE']);

it('بيرفض قيمة مش hex', function (): void {
    expect(app(BrandPalette::class)->meetsTextContrast('not-a-color'))->toBeFalse();
});

it('صفحة المظهر بترفض حفظ لون تباينه ضعيف', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageAppearance::class)
        ->fillForm(['primary_color' => '#FFFF00'])
        ->call('save')
        ->assertHasFormErrors(['primary_color']);
});

it('صفحة المظهر بتقبل لون تباينه كويس', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    themeContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageAppearance::class)
        ->fillForm([
            'primary_color' => '#7B1FA2',
            'font_family' => 'Cairo',
            'default_theme' => 'dark',
            'sidebar_default' => 'collapsed',
            'allow_theme_switch' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});
