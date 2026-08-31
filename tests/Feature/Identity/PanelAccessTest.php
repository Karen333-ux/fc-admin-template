<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Src\Contexts\Identity\Domain\Models\User;
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
