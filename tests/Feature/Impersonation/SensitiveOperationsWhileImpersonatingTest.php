<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * عمليات حساسة معطّلة أثناء الانتحال. (docs/12 بند ٣ قاعدة ٦)
 *
 * ⚠️ العمليات الموجودة فعلاً بس: حذف حساب (`UserPolicy::delete`) وتغيير
 *    كلمة مرور (`UserPolicy::resetPassword`). «عمليات مالية» مؤجّلة —
 *    مفيش أي تنفيذ لها في المشروع، فمفيش حاجة نحميها. (التقرير النهائي)
 */
afterEach(function (): void {
    Impersonation::clear();
});

it('حذف حساب معطّل أثناء الانتحال', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    // $target أصلاً معاه صلاحية delete.users كامل — الاختبار على الحجب
    // وقت الانتحال بس، مش على الصلاحية نفسها
    $target = userWithRole('admin', $tenant);
    $victim = User::factory()->create();
    $victim->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    Impersonation::enter($admin, $target);

    $response = Gate::forUser($target)->inspect('delete', $victim);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->toBe(__('authorization.denied.blocked_while_impersonating'));
});

it('تغيير كلمة المرور معطّل أثناء الانتحال', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = userWithRole('admin', $tenant);
    $victim = User::factory()->create();
    $victim->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    Impersonation::enter($admin, $target);

    $response = Gate::forUser($target)->inspect('resetPassword', $victim);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->toBe(__('authorization.denied.blocked_while_impersonating'));
});

it('حذف حساب مسموح عادي — من غير انتحال', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $victim = User::factory()->create();
    $victim->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($admin)->allows('delete', $victim))->toBeTrue();
});

it('تغيير كلمة المرور مسموح عادي — من غير انتحال', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $victim = User::factory()->create();
    $victim->tenants()->attach($tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($admin)->allows('resetPassword', $victim))->toBeTrue();
});
