<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Exceptions\MissingTenantContextException;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Authorization\TenantBoundary;
use Tests\Fixtures\TenantOwnedRecord;

beforeEach(function (): void {
    // DDL في بوستجرس معاملاتي، فبيترجع مع rollback بتاع RefreshDatabase
    Schema::create('tenant_owned_records', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->timestamps();

        $table->index(['tenant_id', 'id']);
    });
});

it('لا يرى المستخدم إلا أعضاء مؤسسته', function (): void {
    // ⚠️ جوهر الشريحة. User مالوش global scope — العزل يدوي وصريح
    // في UserResource::getEloquentQuery(). (docs/23 بند ٤)
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $onlyA = User::factory()->count(2)->create();
    $onlyA->each(fn (User $u) => $u->tenants()->attach($a));

    $onlyB = User::factory()->count(5)->create();
    $onlyB->each(fn (User $u) => $u->tenants()->attach($b));

    $shared = User::factory()->create();
    $shared->tenants()->attach([$a->getKey(), $b->getKey()]);

    actingWithinTenant($a);

    $visible = UserResource::getEloquentQuery()->pluck('id')->all();

    expect($visible)->toContain($shared->getKey());

    foreach ($onlyA as $user) {
        expect($visible)->toContain($user->getKey());
    }

    foreach ($onlyB as $user) {
        expect($visible)->not->toContain($user->getKey());
    }

    expect($visible)->toHaveCount(3);   // ٢ خاصين بـ a + المشترك
});

it('يرمي استثناء بدل ما يرجّع كل الصفوف بدون سياق', function (): void {
    $tenant = Tenant::factory()->create();

    actingWithinTenant($tenant);
    TenantOwnedRecord::create(['title' => 'داخل السياق']);

    app(TenantContext::class)->set(null);

    TenantOwnedRecord::query()->get();
})->throws(MissingTenantContextException::class);

it('set(null) يمسح السياق فعلاً', function (): void {
    // ADR-008 — من غير فلاج isSet، set(null) بيرجع لمستأجر Filament
    $tenant = Tenant::factory()->create();

    actingWithinTenant($tenant);
    expect(app(TenantContext::class)->id())->toBe($tenant->getKey());

    app(TenantContext::class)->set(null);
    expect(app(TenantContext::class)->id())->toBeNull();
});

it('TenantScope بيفلتر الصفوف على المستأجر الحالي', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    actingWithinTenant($a);
    TenantOwnedRecord::create(['title' => 'بتاع أ']);

    actingWithinTenant($b);
    TenantOwnedRecord::create(['title' => 'بتاع ب']);
    TenantOwnedRecord::create(['title' => 'بتاع ب كمان']);

    expect(TenantOwnedRecord::query()->count())->toBe(2);

    actingWithinTenant($a);
    expect(TenantOwnedRecord::query()->count())->toBe(1);
});

it('لا يتجاوز أي دور حدود المستأجر', function (string $role): void {
    // ADR-005 — المدير العام **مش** استثناء
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    actingWithinTenant($b);
    $foreign = TenantOwnedRecord::create(['title' => 'سجل مؤسسة تانية']);

    $user = userWithRole($role, $a);
    actingWithinTenant($a);

    expect(app(TenantBoundary::class)->crosses($foreign))
        ->toBeTrue("الدور {$role}: الحد مااتحسبش كعبور");
})->with(['super_admin', 'admin', 'editor', 'viewer']);

it('حد المستأجر مابينطبقش على الموديلات العابرة للحدود', function (): void {
    // User و Tenant مالهمش BelongsToTenant — بيعدّوا من غير فحص (ADR-002)
    $tenant = Tenant::factory()->create();
    $user = userWithRole('admin', $tenant);

    actingWithinTenant($tenant);

    expect(app(TenantBoundary::class)->crosses($user))->toBeFalse()
        ->and(app(TenantBoundary::class)->crosses($tenant))->toBeFalse();
});

it('لا تتسرّب الأدوار بين المستأجرين', function (): void {
    // teams => true — نفس المستخدم admin في مستأجر و مالوش دور في التاني
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $user = userWithRole('admin', $a);
    $user->tenants()->attach($b);

    $registrar = app(PermissionRegistrar::class);

    actingWithinTenant($a);
    $registrar->forgetCachedPermissions();
    expect($user->fresh()->can('viewAny', User::class))->toBeTrue();

    actingWithinTenant($b);
    $registrar->forgetCachedPermissions();
    expect($user->fresh()->can('viewAny', User::class))->toBeFalse();
});
