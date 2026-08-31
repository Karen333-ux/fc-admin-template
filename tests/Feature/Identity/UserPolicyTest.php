<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Gate;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;

dataset('user_matrix', [
    ['super_admin', 'viewAny', true],  ['super_admin', 'create', true],
    ['admin',       'viewAny', true],  ['admin',       'create', true],
    ['editor',      'viewAny', true],  ['editor',      'create', false],
    ['viewer',      'viewAny', true],  ['viewer',      'create', false],
]);

it('مصفوفة أدوار المستخدمين', function (string $role, string $ability, bool $allowed): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect($user->can($ability, User::class))
        ->toBe($allowed, "الدور {$role} على {$ability}");
})->with('user_matrix');

it('لا يستطيع أي مستخدم حذف نفسه', function (string $role): void {
    // ده الاختبار اللي بيثبت Gate::before مظبوط. لو المدير العام قدر يحذف
    // نفسه، يبقى invariants() مش شغّال — والقالب كله واقف على النقطة دي.
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect($user->can('delete', $user))->toBeFalse("الدور {$role} قدر يحذف نفسه");
})->with(['super_admin', 'admin']);

it('يقدر يحذف مستخدم تاني لو معاه الصلاحية', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = userWithRole('admin', $tenant);
    $target = userWithRole('viewer', $tenant);

    actingWithinTenant($tenant);

    expect($actor->can('delete', $target))->toBeTrue();
});

it('مايقدرش يعدّل سجل متحذوف', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = userWithRole('admin', $tenant);
    $target = userWithRole('viewer', $tenant);

    $target->delete();

    actingWithinTenant($tenant);

    expect($actor->can('update', $target->fresh()))->toBeFalse();
});

it('رسالة الرفض بتوصل مش مجرد false', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    actingWithinTenant($tenant);

    $response = app(Gate::class)
        ->forUser($viewer)
        ->inspect('create', User::class);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->not->toBeEmpty();
});
