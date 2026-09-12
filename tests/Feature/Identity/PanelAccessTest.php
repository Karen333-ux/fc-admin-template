<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;

it('صفحة دخول اللوحة بتفتح', function (): void {
    $this->get('/admin/login')->assertSuccessful();
});

it('admin يقدر يفتح اللوحة', function (): void {
    // ADR-003 — المصيدة الأصلية: من غير access.panel.admin في الكتالوج
    // محدش غير super_admin كان هيقدر يفتح اللوحة أصلاً.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    actingWithinTenant($tenant);

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('كل دور في الكتالوج بيفتح اللوحة', function (string $role): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
})->with(['super_admin', 'admin', 'editor', 'viewer']);

it('مستخدم بلا دور مايقدرش يفتح اللوحة', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('المستخدم بيشوف مؤسساته بس في مبدّل المستأجر', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $user = userWithRole('admin', $a);

    expect($user->getTenants(Filament::getPanel('admin'))->pluck('id')->all())
        ->toBe([$a->getKey()])
        ->and($user->canAccessTenant($b))->toBeFalse()
        ->and($user->canAccessTenant($a))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| الدخول من غير سياق مستأجر — ADR-026
|--------------------------------------------------------------------------
|
| كل اختبار فوق بينادي actingWithinTenant() قبل canAccessPanel، فبيقيس
| الحالة اللي بعد اختيار المستأجر. Filament بينادي canAccessPanel وقت
| **الدخول** — قبل ما يبقى فيه مستأجر أصلاً. الفرق ده هو اللي خلّى عيباً
| يمنع أي حد من فتح اللوحة يعدّي من تحت ثمانية اختبارات خضرا.
|
*/

it('يفتح اللوحة من غير سياق مستأجر — زي وقت الدخول بالظبط', function (): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole('super_admin', $tenant);

    // ولا actingWithinTenant ولا أي حاجة تانية: ده هو الفرق كله.
    app(TenantContext::class)->forget();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('يفتح اللوحة لو الدور في أي مستأجر مش الأول بس', function (): void {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    $user = userWithRole('admin', $second);
    $user->tenants()->attach($first);

    app(TenantContext::class)->forget();

    expect($user->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('مايفتحش اللوحة لو مش تابع لأي مستأجر', function (): void {
    $user = User::factory()->create();

    app(TenantContext::class)->forget();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('بيرجّع سياق المستأجر زي ما كان بعد الفحص', function (): void {
    // الفحص بيلف على المستأجرين وبيغيّر السياق مع كل واحد. لو ما رجّعهوش،
    // باقي الطلب بيشتغل بسياق آخر مستأجر اتفحص — تسريب صامت بين المستأجرين.
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $user = userWithRole('super_admin', $tenant);

    actingWithinTenant($other);

    $user->canAccessPanel(Filament::getPanel('admin'));

    expect(app(TenantContext::class)->id())->toBe($other->getKey());
});
