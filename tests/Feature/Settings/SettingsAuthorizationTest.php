<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Gate;
use Src\Support\Domain\Models\Tenant;

dataset('appearance_matrix', [
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('مصفوفة أدوار manage_appearance.settings', function (string $role, bool $allowed): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('manage_appearance.settings'))
        ->toBe($allowed, "الدور {$role} على manage_appearance.settings");
})->with('appearance_matrix');

it('Gate الكتالوج بترجّع Response برسالة رفض مش bool', function (): void {
    // ADR-019 — القدرات اللي مالهاش موديل بتتفحص بالـ Gate مباشرةً،
    // فـ bool كان بيضيّع سبب الرفض على صفحات الإعدادات.
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    actingWithinTenant($tenant);

    $response = app(GateContract::class)
        ->forUser($viewer)
        ->inspect('manage_appearance.settings');

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->not->toBeEmpty()
        ->and($response->message())->not->toContain('authorization.');
});

it('Gate الكتالوج بترجّع Response بالسماح لمن يملك الصلاحية', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    actingWithinTenant($tenant);

    $response = app(GateContract::class)
        ->forUser($admin)
        ->inspect('manage_appearance.settings');

    expect($response->allowed())->toBeTrue();
});

it('viewer معاه view_any.settings بس مش manage_appearance', function (): void {
    // بيثبت إن أنماط الأدوار في الكتالوج بتفرّق بين القراءة والإدارة
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($viewer)->allows('view_any.settings'))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('manage_appearance.settings'))->toBeFalse();
});
