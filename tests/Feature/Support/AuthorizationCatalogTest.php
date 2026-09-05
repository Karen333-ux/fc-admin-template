<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Authorization\PermissionBuilder;

it('كل صلاحية في الكتالوج لها Gate معرّف', function (): void {
    // ADR-003 — من غير ده، access.panel.admin مالهاش Gate ومحدش بيفتح اللوحة
    $names = app(PermissionBuilder::class)->allPermissionNames();

    expect($names)->not->toBeEmpty();

    foreach ($names as $ability) {
        expect(Gate::has($ability))->toBeTrue("مفيش Gate للصلاحية {$ability}");
    }
});

it('كل صلاحية مترجمة ar و en', function (): void {
    $original = App::currentLocale();

    foreach (app(PermissionBuilder::class)->allPermissionNames() as $ability) {
        foreach (['ar', 'en'] as $locale) {
            App::setLocale($locale);

            expect(permission_label($ability))
                ->not->toContain('authorization.', "الصلاحية {$ability} مش مترجمة في {$locale}");
        }
    }

    App::setLocale($original);
});

it('*.users توسّع لكل أفعال users فقط', function (): void {
    // ADR-001 — الشق الشمال فعل واليمين مورد. users.* غلط.
    $expanded = app(PermissionBuilder::class)->expandPatterns(['*.users']);

    expect($expanded)->toHaveCount(12);       // ٩ افتراضية + ٣ extra

    foreach ($expanded as $name) {
        expect($name)->toEndWith('.users');
    }
});

it('access.* توسّع لصلاحيات الصفحات فقط', function (): void {
    $expanded = app(PermissionBuilder::class)->expandPatterns(['access.*']);

    // access.health اتضافت في Slice 4.10 (docs/11 بند ٧).
    // access.horizon اتضافت في Slice 5.1 (docs/13 بند ١).
    expect($expanded)->toEqualCanonicalizing([
        'access.panel.admin',
        'access.dashboard',
        'access.health',
        'access.horizon',
    ]);
});

// ────────────────────────────────────────────────────────────────
// سلوك الفشل — الفحصين لازم يفضلوا متّسقين
// ────────────────────────────────────────────────────────────────

it('صلاحية في الكتالوج ولسه ماتزامنتش بترفض مش بترمي', function (): void {
    // المسارين (Gate و Decision) لازم يدّوا نفس النتيجة على نفس المدخل.
    // الـ Gate كان بيمسك PermissionDoesNotExist، و Decision لأ — فنسيان
    // `authorization:sync` كان بيدّي 500 بدل رفض نظيف.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    actingWithinTenant($tenant);

    expect($admin->can('create', User::class))->toBeTrue();

    // الصلاحية لسه معرّفة في الكونفيج، بس الصف اتشال من قاعدة البيانات
    Permission::query()->where('name', 'create.users')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Decision (عبر الـ Policy)
    expect($admin->can('create', User::class))->toBeFalse();

    // الـ Gate المباشر على اسم الصلاحية
    expect(Gate::forUser($admin)->allows('create.users'))->toBeFalse();
});

it('authorization:sync بيرجّع team id بتاع الصلاحيات', function (): void {
    // الأمر بيصفّر الـ team عشان الأدوار تتعمل تعريفات عامة. لو مارجّعش
    // القيمة، أي فحص صلاحية بعده في نفس العملية بيفشل بصمت.
    $tenant = Tenant::factory()->create();
    $registrar = app(PermissionRegistrar::class);

    $registrar->setPermissionsTeamId($tenant->getKey());

    Artisan::call('authorization:sync');

    expect($registrar->getPermissionsTeamId())->toBe($tenant->getKey());
});

it('authorization:sync بيرجّع team id حتى لو رمى استثناء', function (): void {
    $tenant = Tenant::factory()->create();
    $registrar = app(PermissionRegistrar::class);

    $registrar->setPermissionsTeamId($tenant->getKey());

    // كتالوج مكسور → الأمر بيرمي جوّه try، والـ finally لازم يرجّع الـ team
    config()->set('authorization.roles', ['broken' => new stdClass]);

    try {
        Artisan::call('authorization:sync');
    } catch (Throwable) {
        // متوقّع — اللي بنختبره هو الاسترجاع مش الاستثناء
    }

    expect($registrar->getPermissionsTeamId())->toBe($tenant->getKey());
});
