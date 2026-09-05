<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Presentation\Filament\Pages\HorizonPage;

/**
 * الدخول للوحة Horizon. (docs/13 بند ١)
 */
function horizonContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

it('مصفوفة أدوار access.horizon', function (string $role, bool $expected): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('access.horizon'))->toBe($expected, "الدور {$role}");
})->with([
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('viewHorizon بتتبع access.horizon مش قايمة إيميلات ثابتة', function (string $role, bool $expected): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBe($expected, "الدور {$role}");
})->with([
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('HorizonPage::canAccess بيوافق admin ومايوافقش viewer', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $viewer = userWithRole('viewer', $tenant);

    horizonContext($tenant, $admin);
    expect(HorizonPage::canAccess())->toBeTrue();

    horizonContext($tenant, $viewer);
    expect(HorizonPage::canAccess())->toBeFalse();
});

it('صفحة Horizon جوّه اللوحة بتعمل redirect لمسار /horizon', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    horizonContext($tenant, $admin);

    Livewire::actingAs($admin)->test(HorizonPage::class)->assertRedirect('/horizon');
});

it('/horizon متاح لدور عنده access.horizon', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    $this->actingAs($admin)->get('/horizon')->assertSuccessful();
});

it('/horizon ممنوع على دور من غير access.horizon', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    $this->actingAs($viewer)->get('/horizon')->assertForbidden();
});

it('/horizon ممنوع من غير مصادقة أصلاً', function (): void {
    $this->get('/horizon')->assertForbidden();
});

it('أولويات الطوابير في بيئة الإنتاج زي docs/13 بند ١ بالظبط', function (): void {
    $environments = config('horizon.environments.production');

    expect(array_keys($environments))->toBe([
        'supervisor-critical',
        'supervisor-notifications',
        'supervisor-media',
        'supervisor-default',
    ])
        ->and($environments['supervisor-critical']['queue'])->toBe(['critical'])
        ->and($environments['supervisor-notifications']['queue'])->toBe(['notifications'])
        ->and($environments['supervisor-media']['queue'])->toBe(['media'])
        ->and($environments['supervisor-default']['queue'])->toBe(['default', 'exports']);
});
