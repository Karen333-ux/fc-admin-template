<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Src\Contexts\Settings\Application\GeneralResolver;
use Src\Contexts\Settings\Domain\Settings\AppearanceSettings;
use Src\Contexts\Settings\Domain\Settings\GeneralSettings;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;

// ────────────────────────────────────────────────────────────────
// التسجيل والقيم الافتراضية
// ────────────────────────────────────────────────────────────────

it('GeneralSettings متسجّلة وبتتحل من الحاوية', function (): void {
    expect(config('settings.settings'))->toContain(GeneralSettings::class);

    expect(app(GeneralSettings::class))->toBeInstanceOf(GeneralSettings::class);
});

it('الهجرة بتزرع القيم الافتراضية الموثّقة', function (): void {
    $settings = app(GeneralSettings::class);

    expect($settings->app_name)->toHaveKeys(['ar', 'en'])
        ->and($settings->app_name['en'])->toBe('Future Code')
        ->and($settings->default_locale)->toBe('ar')
        ->and($settings->timezone)->toBe('Africa/Cairo')
        // إعداد بيقفل التطبيق مايبدأش مقفول
        ->and($settings->maintenance_mode)->toBeFalse()
        ->and($settings->maintenance_message)->toHaveKeys(['ar', 'en']);
});

it('مفيش أسرار في المجموعة دي', function (): void {
    // الحقول كلها بتتعرض في اللوحة — أول ما يدخل سر لازم يتحط في encrypted()
    expect(GeneralSettings::encrypted())->toBe([])
        ->and(GeneralSettings::group())->toBe('general');
});

// ────────────────────────────────────────────────────────────────
// الصلاحية في الكتالوج
// ────────────────────────────────────────────────────────────────

it('manage_general.settings موجودة في الكتالوج ومترجمة', function (): void {
    $extras = config('authorization.resources.settings.extra');

    expect($extras)->toContain('manage_general');

    // الترجمة موجودة في اللغتين — مش راجعة المفتاح نفسه
    foreach (['ar', 'en'] as $locale) {
        $label = trans('authorization.actions.manage_general', [], $locale);

        expect($label)->not->toBe('authorization.actions.manage_general')
            ->and($label)->not->toBeEmpty();
    }

    expect(trans('authorization.actions.manage_general', [], 'ar'))
        ->not->toBe(trans('authorization.actions.manage_general', [], 'en'));
});

// ────────────────────────────────────────────────────────────────
// عامة مش لكل مستأجر — الحد المعماري
// ────────────────────────────────────────────────────────────────

it('الإعدادات العامة واحدة لكل المستأجرين', function (): void {
    // ⚠️ الجوهر: لو القيمة اتغيّرت مع تغيير المستأجر، يبقى إعداد التثبيت
    // بقى قابل للدهس من جوّه مستأجر — وده تجاوز للحدود مش ميزة.
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $settings = app(GeneralSettings::class);
    $settings->app_name = ['ar' => 'اسم عام', 'en' => 'Global name'];
    $settings->save();

    actingWithinTenant($a);
    $fromA = app(GeneralSettings::class)->refresh()->app_name;

    actingWithinTenant($b);
    $fromB = app(GeneralSettings::class)->refresh()->app_name;

    expect($fromA)->toBe($fromB)
        ->and($fromA['en'])->toBe('Global name');
});

it('حفظ الإعدادات العامة مابيكتبش أي صف في tenant_settings', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    $settings = app(GeneralSettings::class);
    $settings->support_email = 'ops@example.test';
    $settings->save();

    // مفيش طبقة مستأجر تحت الإعدادات العامة خالص
    expect(DB::table('tenant_settings')->where('group', 'general')->count())->toBe(0);
});

it('الإعدادات العامة بتتقرا من غير سياق مستأجر', function (): void {
    // ماينفعش إعداد تثبيت يعتمد على وجود مستأجر — الأوامر والطوابير
    // بتشتغل من غير سياق.
    app(TenantContext::class)->set(null);

    expect(app(GeneralSettings::class)->app_name)->toHaveKey('en');
});

// ────────────────────────────────────────────────────────────────
// سلوك اللغة
// ────────────────────────────────────────────────────────────────

it('اسم التطبيق بيتبع اللغة الحالية ويرجع للاحتياطية', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->app_name = ['ar' => 'كود المستقبل', 'en' => 'Future Code'];
    $settings->save();

    $resolver = app(GeneralResolver::class);

    expect($resolver->appName('ar', 'en'))->toBe('كود المستقبل')
        ->and($resolver->appName('en', 'en'))->toBe('Future Code')
        // لغة مش موجودة → الاحتياطية
        ->and($resolver->appName('fr', 'en'))->toBe('Future Code');
});

it('اسم فاضي بيرجع لاسم التطبيق من الكونفيج بدل ما يفضّي رأس اللوحة', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->app_name = [];
    $settings->save();

    expect(app(GeneralResolver::class)->appName('ar', 'en'))
        ->toBe((string) config('app.name'))
        ->not->toBeEmpty();
});

// ────────────────────────────────────────────────────────────────
// brandName — التكامل مع اللوحة
// ────────────────────────────────────────────────────────────────

it('brandName بتاعت اللوحة بتتحل من GeneralSettings', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->app_name = ['ar' => 'لوحة الاختبار', 'en' => 'Test Panel'];
    $settings->save();

    app()->setLocale('en');
    Filament::setCurrentPanel('admin');

    expect(Filament::getCurrentPanel()?->getBrandName())->toBe('Test Panel');

    app()->setLocale('ar');

    // نفس اللوحة، لغة تانية — الكلوجر بيتقيّم وقت العرض مش وقت التسجيل
    expect(Filament::getCurrentPanel()?->getBrandName())->toBe('لوحة الاختبار');
});

it('المظهر لسه شغّال زي ما هو بعد إضافة الإعدادات العامة', function (): void {
    // اختبار تراجُع: الشريحة دي مالهاش حق تلمس سلوك المظهر
    $appearance = app(AppearanceSettings::class);

    expect($appearance->primary_color)->toBeString()->not->toBeEmpty()
        ->and($appearance->font_family)->toBeString()->not->toBeEmpty()
        ->and($appearance->footer_text)->toHaveKeys(['ar', 'en']);

    Filament::setCurrentPanel('admin');

    expect(Filament::getCurrentPanel()?->getFontFamily())->toBe($appearance->font_family);
});
