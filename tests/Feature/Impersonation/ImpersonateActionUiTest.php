<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUsers;
use Src\Support\Domain\Models\Tenant;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * زرار الانتحال على جدول UserResource — التكامل الفعلي بين الحزمة
 * والـ Policy. (docs/12 بند ٣)
 */
function impersonateUiContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

afterEach(function (): void {
    Impersonation::clear();
});

it('admin بيقدر ينتحل مستخدم من جدول القايمة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    impersonateUiContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->callTableAction('impersonate', $target);

    expect(Auth::id())->toBe($target->getKey());
});

it('عضو بلا صلاحية impersonate مايقدرش ينتحل من الجدول', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    impersonateUiContext($tenant, $viewer);

    Livewire::actingAs($viewer)
        ->test(ListUsers::class)
        ->assertTableActionHidden('impersonate', $target);

    expect(Auth::id())->toBe($viewer->getKey());
});

it('زرار الانتحال مخفي لمدير عام مستهدف', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $superAdmin = userWithRole('super_admin', $tenant);

    impersonateUiContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertTableActionHidden('impersonate', $superAdmin);
});
