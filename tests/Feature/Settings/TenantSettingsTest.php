<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Contexts\Settings\Application\TenantSettings;
use Src\Contexts\Settings\Domain\Models\TenantSetting;
use Src\Contexts\Settings\Domain\Settings\AppearanceSettings;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Exceptions\MissingTenantContextException;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Authorization\TenantBoundary;

// ────────────────────────────────────────────────────────────────
// BelongsToTenant — أول موديل إنتاجي تابع لمستأجر
// ────────────────────────────────────────────────────────────────

it('بيحط tenant_id تلقائياً من السياق', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    $setting = TenantSetting::create([
        'group' => 'appearance',
        'key' => 'primary_color',
        'value' => '#FF0000',
    ]);

    expect($setting->getAttribute('tenant_id'))->toBe($tenant->getKey());
});

it('tenant_id مش قابل للتعيين الجماعي', function (): void {
    // ADR-020: الـ trait بيحطه، مش mass-assignment
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    actingWithinTenant($a);

    $setting = TenantSetting::create([
        'tenant_id' => $b->getKey(),      // ← محاولة زرع في مستأجر تاني
        'group' => 'appearance',
        'key' => 'primary_color',
        'value' => '#FF0000',
    ]);

    expect($setting->getAttribute('tenant_id'))->toBe($a->getKey())
        ->and($setting->getAttribute('tenant_id'))->not->toBe($b->getKey());
});

it('TenantScope بيفلتر إعدادات كل مستأجر', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    actingWithinTenant($a);
    TenantSetting::create(['group' => 'appearance', 'key' => 'primary_color', 'value' => '#AAA111']);

    actingWithinTenant($b);
    TenantSetting::create(['group' => 'appearance', 'key' => 'primary_color', 'value' => '#BBB222']);

    expect(TenantSetting::query()->count())->toBe(1)
        ->and(TenantSetting::query()->first()->value)->toBe('#BBB222');

    actingWithinTenant($a);
    expect(TenantSetting::query()->first()->value)->toBe('#AAA111');
});

it('استعلام بدون سياق بيرمي مش بيرجّع صفوف', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);
    TenantSetting::create(['group' => 'appearance', 'key' => 'primary_color', 'value' => '#AAA111']);

    app(TenantContext::class)->set(null);

    TenantSetting::query()->get();
})->throws(MissingTenantContextException::class);

it('TenantBoundary بيشوف سجل مستأجر تاني كعبور', function (): void {
    // أول مرة الحد ده يتجرّب على موديل **إنتاجي**، مش فيكستشر اختبارات
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    actingWithinTenant($b);
    $foreign = TenantSetting::create(['group' => 'appearance', 'key' => 'primary_color', 'value' => '#BBB222']);

    actingWithinTenant($a);

    expect(app(TenantBoundary::class)->crosses($foreign))->toBeTrue();
});

// ────────────────────────────────────────────────────────────────
// TenantSettings — القراءة والرجوع للعام
// ────────────────────────────────────────────────────────────────

it('بيرجع للقيمة العامة لو المستأجر مالوش إعداد', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    $global = app(AppearanceSettings::class)->primary_color;

    expect(app(TenantSettings::class)->get('appearance', 'primary_color'))->toBe($global);
});

it('بيرجع للقيمة العامة لو مفيش سياق مستأجر', function (): void {
    app(TenantContext::class)->set(null);

    $global = app(AppearanceSettings::class)->primary_color;

    expect(app(TenantSettings::class)->get('appearance', 'primary_color'))->toBe($global);
});

it('إعداد المستأجر بيدهس العام', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    app(TenantSettings::class)->set('appearance', 'primary_color', '#123456');

    expect(app(TenantSettings::class)->get('appearance', 'primary_color'))->toBe('#123456');
});

it('بيرجع القيمة الافتراضية لمجموعة مش معرّفة', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    expect(app(TenantSettings::class)->get('nope', 'missing', 'fallback'))->toBe('fallback');
});

it('كاش الإعدادات معزول بين المستأجرين', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    actingWithinTenant($a);
    app(TenantSettings::class)->set('appearance', 'primary_color', '#AAA111');
    expect(app(TenantSettings::class)->get('appearance', 'primary_color'))->toBe('#AAA111');

    // المستأجر التاني مايشوفش قيمة الأول — لا من الجدول ولا من الكاش
    actingWithinTenant($b);
    expect(app(TenantSettings::class)->get('appearance', 'primary_color'))
        ->toBe(app(AppearanceSettings::class)->primary_color);
});

// ────────────────────────────────────────────────────────────────
// سلوك قاعدة البيانات
// ────────────────────────────────────────────────────────────────

it('القيم بترجع بأنواعها من عمود json', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    $settings = app(TenantSettings::class);
    $settings->set('appearance', 'allow_theme_switch', false);
    $settings->set('appearance', 'footer_text', ['ar' => 'نص', 'en' => 'text']);

    expect($settings->get('appearance', 'allow_theme_switch'))->toBeFalse()
        ->and($settings->get('appearance', 'footer_text'))->toBe(['ar' => 'نص', 'en' => 'text']);
});

it('مفيش تكرار لنفس (المستأجر، المجموعة، المفتاح)', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    TenantSetting::create(['group' => 'appearance', 'key' => 'primary_color', 'value' => '#AAA111']);
    TenantSetting::create(['group' => 'appearance', 'key' => 'primary_color', 'value' => '#BBB222']);
})->throws(QueryException::class);

it('حذف المستأجر بيحذف إعداداته', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);
    TenantSetting::create(['group' => 'appearance', 'key' => 'primary_color', 'value' => '#AAA111']);

    expect(DB::table('tenant_settings')->count())->toBe(1);

    $tenant->forceDelete();

    expect(DB::table('tenant_settings')->count())->toBe(0);
});
