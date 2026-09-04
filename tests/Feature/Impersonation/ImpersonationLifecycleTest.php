<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Authorization\ImpersonationContext;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * دورة حياة الانتحال — بداية ونهاية. (docs/12 بند ٣)
 */
afterEach(function (): void {
    Impersonation::clear();
});

it('البداية بتخلّي المستخدم الهدف هو المصادَق عليه', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target);

    expect(Auth::id())->toBe($target->getKey());
});

it('المستخدم الأصلي محفوظ وقت الانتحال', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target);

    expect(Impersonation::getImpersonatorId())->toBe($admin->getKey());
});

it('إنهاء الانتحال بيرجّع المستخدم الأصلي', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target);
    expect(Auth::id())->toBe($target->getKey());

    Impersonation::leave();

    expect(Auth::id())->toBe($admin->getKey())
        ->and(Impersonation::isImpersonating())->toBeFalse();
});

it('المستأجر الهدف بيتسجّل صح وقت البداية', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    Impersonation::enter($admin, $target);

    expect(app(ImpersonationContext::class)->targetTenantId())
        ->toBe($tenant->getKey());
});
