<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Src\Contexts\Settings\Domain\Settings\StorageSettings;
use Src\Contexts\Settings\Presentation\Filament\Pages\ManageStorage;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function storageContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

/** حمولة صالحة كاملة للفورم */
function storageFormData(array $overrides = []): array
{
    return array_merge([
        'default_disk' => 'public',
        'private_disk' => 'local',
        'private_collections' => ['documents'],
        'max_upload_size_kb' => 2048,
        'allowed_mimes' => ['image/png', 'application/pdf'],
    ], $overrides);
}

// ────────────────────────────────────────────────────────────────
// التفويض — الرفض الأول
// ────────────────────────────────────────────────────────────────

dataset('storage_matrix', [
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('مصفوفة أدوار manage_storage.settings', function (string $role, bool $allowed): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('manage_storage.settings'))
        ->toBe($allowed, "الدور {$role} على manage_storage.settings");
})->with('storage_matrix');

it('يمنع من لا يملك manage_storage من فتح الصفحة', function (string $role): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    storageContext($tenant, $user);

    expect(ManageStorage::canAccess())->toBeFalse();
})->with(['editor', 'viewer']);

it('يمنع الوصول بالـ URL المباشر لمن لا يملك الصلاحية', function (): void {
    // إخفاء اللينك مابيقفلش الـ endpoint (docs/04 بند ٨)
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    $this->actingAs($viewer)
        ->get(ManageStorage::getUrl(tenant: $tenant))
        ->assertForbidden();
});

it('الرفض بيعدّي على Response برسالة مترجمة مش bool', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    actingWithinTenant($tenant);

    $response = app(GateContract::class)
        ->forUser($viewer)
        ->inspect('manage_storage.settings');

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->not->toBeEmpty()
        ->and($response->message())->not->toContain('authorization.');
});

it('admin بيفتح صفحة التخزين', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    storageContext($tenant, $admin);

    expect(ManageStorage::canAccess())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ManageStorage::class)
        ->assertOk();
});

// ────────────────────────────────────────────────────────────────
// مصدر الديسكات — الكونفيج مش الكود
// ────────────────────────────────────────────────────────────────

it('اختيارات الديسك جاية من config/filesystems.php', function (): void {
    // معيار docs/04 بند ١٠: مفيش اسم ديسك مكتوب في الكود
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    storageContext($tenant, $admin);

    $expected = array_keys((array) config('filesystems.disks'));

    // ديسك جديد في الكونفيج لازم يظهر من غير أي تعديل في الصفحة
    config(['filesystems.disks.archive' => ['driver' => 'local', 'root' => storage_path('app/archive')]]);

    $withNewDisk = array_keys((array) config('filesystems.disks'));

    expect($expected)->toContain('public', 'local', 's3', 's3-private')
        ->and($withNewDisk)->toContain('archive');

    // والصفحة بتقبل الديسك الجديد لأن الاختيارات مبنية وقت العرض
    Livewire::actingAs($admin)
        ->test(ManageStorage::class)
        ->fillForm(storageFormData(['default_disk' => 'archive']))
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(StorageSettings::class)->refresh()->default_disk)->toBe('archive');
});

// ────────────────────────────────────────────────────────────────
// الحفظ والتحقق
// ────────────────────────────────────────────────────────────────

it('حفظ الصفحة بيغيّر إعدادات التخزين العامة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    storageContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageStorage::class)
        ->fillForm(storageFormData([
            'default_disk' => 's3',
            'max_upload_size_kb' => 5120,
            'allowed_mimes' => ['image/webp'],
        ]))
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(StorageSettings::class)->refresh();

    expect($settings->default_disk)->toBe('s3')
        ->and($settings->private_disk)->toBe('local')
        ->and($settings->private_collections)->toBe(['documents'])
        ->and($settings->max_upload_size_kb)->toBe(5120)
        ->and($settings->allowed_mimes)->toBe(['image/webp']);
});

it('ديسك خاص مابيدعمش الروابط المؤقتة بيترفض', function (): void {
    // ⚠️ ديسك public محلي من غير serve — `MediaUrlResolver` كان هيرمي عند
    // أول تحميل (ADR-022 بند ٥). الرفض هنا بيمسكها وقت الإعداد.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    storageContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageStorage::class)
        ->fillForm(storageFormData(['private_disk' => 'public']))
        ->call('save')
        ->assertHasFormErrors(['private_disk']);
});

it('SVG مرفوض في الأنواع المسموحة', function (): void {
    // CLAUDE.md · docs/20 — SVG في مجموعة عامة ثغرة XSS مخزّنة
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    storageContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageStorage::class)
        ->fillForm(storageFormData(['allowed_mimes' => ['image/png', 'image/svg+xml']]))
        ->call('save')
        ->assertHasFormErrors(['allowed_mimes']);

    // والإعداد المحفوظ ما اتغيّرش
    expect(app(StorageSettings::class)->refresh()->allowed_mimes)
        ->not->toContain('image/svg+xml');
});

it('حجم رفع أقل من واحد بيترفض', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    storageContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageStorage::class)
        ->fillForm(storageFormData(['max_upload_size_kb' => 0]))
        ->call('save')
        ->assertHasFormErrors(['max_upload_size_kb']);
});

// ────────────────────────────────────────────────────────────────
// عامة مش لكل مستأجر
// ────────────────────────────────────────────────────────────────

it('إعدادات التخزين واحدة لكل المستأجرين', function (): void {
    // ⚠️ مستأجر يقدر يغيّر الديسك = يقدر يوجّه ملفات التثبيت كله لتخزينه هو
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $settings = app(StorageSettings::class);
    $settings->default_disk = 's3';
    $settings->save();

    actingWithinTenant($a);
    $fromA = app(StorageSettings::class)->refresh()->default_disk;

    actingWithinTenant($b);
    $fromB = app(StorageSettings::class)->refresh()->default_disk;

    expect($fromA)->toBe($fromB)->toBe('s3');
});

it('الحفظ من الصفحة مابيكتبش صف في tenant_settings', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    storageContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageStorage::class)
        ->fillForm(storageFormData())
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('tenant_settings')->where('group', 'storage')->count())->toBe(0);
});

it('إعدادات التخزين بتتقرا من غير سياق مستأجر', function (): void {
    // الطوابير والأوامر بتكتب ملفات من غير سياق مستأجر
    app(TenantContext::class)->set(null);

    expect(app(StorageSettings::class)->default_disk)->toBeString()->not->toBeEmpty();
});

// ────────────────────────────────────────────────────────────────
// الترجمة والنطاق
// ────────────────────────────────────────────────────────────────

it('عنوان الصفحة وتسميات التخزين مترجمة في اللغتين', function (): void {
    foreach ([
        'settings::settings.pages.storage',
        'settings::settings.fields.default_disk',
        'settings::settings.validation.forbidden_mime',
    ] as $key) {
        expect(trans($key, [], 'ar'))->not->toBe($key)
            ->and(trans($key, [], 'en'))->not->toBe($key)
            ->and(trans($key, [], 'ar'))->not->toBe(trans($key, [], 'en'));
    }
});

it('مفيش زرار اختبار اتصال — مؤجّل صراحةً', function (): void {
    // ADR-022 بند ٦: الزرار ده بيكتب ويمسح ملف حقيقي على الديسك، ومؤجّل
    $source = file_get_contents(
        base_path('src/Contexts/Settings/Presentation/Filament/Pages/ManageStorage.php'),
    );

    expect($source)->not->toContain('testConnection')
        ->and($source)->not->toContain('test_connection');
});
