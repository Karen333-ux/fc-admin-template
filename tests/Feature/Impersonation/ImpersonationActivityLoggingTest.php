<?php

declare(strict_types=1);

use Spatie\Activitylog\Models\Activity;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * تسجيل بداية ونهاية الانتحال في activity('security'). (docs/12 بند ٣ قاعدة ٤)
 */
afterEach(function (): void {
    Impersonation::clear();
});

it('بداية الانتحال بتتسجّل تحت security', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    Impersonation::enter($admin, $target);

    $activity = Activity::query()->where('event', 'impersonation_started')->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security')
        ->and($activity->description)->toBe(__('audit.events.user.impersonation_started'))
        ->and($activity->subject_id)->toBe($target->getKey())
        ->and($activity->causer_id)->toBe($admin->getKey());
});

it('مستأجر الهدف بيتسجّل في نشاط البداية', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    Impersonation::enter($admin, $target);

    $activity = Activity::query()->where('event', 'impersonation_started')->latest()->first();

    expect($activity->properties['tenant_id'])->toBe($tenant->getKey());
});

it('نهاية الانتحال بتتسجّل تحت security', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    Impersonation::enter($admin, $target);
    Impersonation::leave();

    $activity = Activity::query()->where('event', 'impersonation_stopped')->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security')
        ->and($activity->description)->toBe(__('audit.events.user.impersonation_stopped'))
        ->and($activity->causer_id)->toBe($admin->getKey());
});

it('مستأجر الهدف بيتسجّل صح في نشاط النهاية كمان — رغم إن مسار الخروج بره InitializeTenantContext', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    Impersonation::enter($admin, $target);

    // بنمسح TenantContext يدوياً — مطابقة لحقيقة إن مسار الخروج بره
    // middleware المستأجر أصلاً، فمالوش قيمة موروثة من الطلب اللي فاته
    app(TenantContext::class)->forget();

    Impersonation::leave();

    $activity = Activity::query()->where('event', 'impersonation_stopped')->latest()->first();

    expect($activity->properties['tenant_id'])->toBe($tenant->getKey());
});
