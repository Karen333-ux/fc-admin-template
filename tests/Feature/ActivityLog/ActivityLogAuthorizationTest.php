<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUserActivities;
use Src\Support\Domain\Models\Tenant;

/**
 * مصفوفة صلاحيات view_any/view/prune على سجل النشاط، ومنع الوصول
 * بالـ URL المباشر. (docs/11 بند ٧)
 */
function activityContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

dataset('activity_view_matrix', [
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', true], // viewer عنده view_any.* / view.* — نمط موجود مسبقاً
]);

it('مصفوفة أدوار view.activity_logs', function (string $role, bool $allowed): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('view.activity_logs'))->toBe($allowed, "الدور {$role}");
})->with('activity_view_matrix');

it('prune.activity_logs مقصورة على super_admin بس', function (): void {
    $tenant = Tenant::factory()->create();

    $superAdmin = userWithRole('super_admin', $tenant);
    $admin = userWithRole('admin', $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($superAdmin)->allows('prune.activity_logs'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('prune.activity_logs'))->toBeFalse();
});

it('admin بيقدر يفتح صفحة نشاط مستخدم', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    activityContext($tenant, $admin);

    expect(ListUserActivities::canAccess())->toBeTrue();
});

it('editor مايقدرش يفتح صفحة نشاط مستخدم', function (): void {
    $tenant = Tenant::factory()->create();
    $editor = userWithRole('editor', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    activityContext($tenant, $editor);

    expect(ListUserActivities::canAccess())->toBeFalse();
});

it('يمنع الوصول بالـ URL المباشر لمن لا يملك view.activity_logs', function (): void {
    $tenant = Tenant::factory()->create();
    $editor = userWithRole('editor', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    $this->actingAs($editor)
        ->get(UserResource::getUrl(
            'activity',
            ['record' => $target],
            tenant: $tenant,
        ))
        ->assertForbidden();
});

it('الرفض بيعدّي على Response برسالة مترجمة مش bool', function (): void {
    $tenant = Tenant::factory()->create();
    $editor = userWithRole('editor', $tenant);

    actingWithinTenant($tenant);

    $response = app(Illuminate\Contracts\Auth\Access\Gate::class)
        ->forUser($editor)
        ->inspect('view.activity_logs');

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->not->toBeEmpty()
        ->and($response->message())->not->toContain('authorization.');
});
