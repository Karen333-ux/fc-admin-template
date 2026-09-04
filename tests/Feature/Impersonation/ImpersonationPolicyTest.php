<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Authorization\ImpersonationContext;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * UserPolicy::impersonate() — قواعد docs/12 بند ٣ الستة. (ADR-005)
 */
afterEach(function (): void {
    Impersonation::clear();
});

it('مينفعش تنتحل نفسك', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($admin)->allows('impersonate', $admin))->toBeFalse();
});

it('مينفعش تنتحل مدير عام', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $superAdmin = userWithRole('super_admin', $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($admin)->allows('impersonate', $superAdmin))->toBeFalse();
});

it('مينفعش مدير عام ينتحل مدير عام تاني — حتى مع تجاوز Gate::before', function (): void {
    // ⚠️ الجوهر: impersonate مدرجة في invariants() فمابتتجاوزش. لو مش
    // كذا، كان Gate::before هيرجّع true قبل ما الـ Policy تشتغل أصلاً.
    $tenant = Tenant::factory()->create();
    $superAdminA = userWithRole('super_admin', $tenant);
    $superAdminB = userWithRole('super_admin', $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($superAdminA)->allows('impersonate', $superAdminB))->toBeFalse();
});

it('مينفعش تبدأ انتحال وانت لسه منتحل', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target1 = User::factory()->create();
    $target1->tenants()->attach($tenant);
    $target2 = User::factory()->create();
    $target2->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    Impersonation::enter($admin, $target1);

    expect(Gate::forUser($admin)->allows('impersonate', $target2))->toBeFalse();
});

it('انتحال صالح مسموح', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($admin)->allows('impersonate', $target))->toBeTrue();
});

it('مفروضة الصلاحية — من غيرها ممنوع حتى لو القواعد التانية كلها سليمة', function (string $role): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('impersonate', $target))->toBeFalse();
})->with(['viewer']);

it('الرفض بيعدّي على Response برسالة مترجمة مش bool', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    actingWithinTenant($tenant);

    $response = app(Illuminate\Contracts\Auth\Access\Gate::class)
        ->forUser($admin)
        ->inspect('impersonate', $admin);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->toBe(__('authorization.denied.self_target'));
});

it('ImpersonationContext::isActive بتعكس حالة الحزمة الحقيقية', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    expect(app(ImpersonationContext::class)->isActive())->toBeFalse();

    Impersonation::enter($admin, $target);

    expect(app(ImpersonationContext::class)->isActive())->toBeTrue();

    Impersonation::leave();

    expect(app(ImpersonationContext::class)->isActive())->toBeFalse();
});
