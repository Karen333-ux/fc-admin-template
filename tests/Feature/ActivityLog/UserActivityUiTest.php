<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUserActivities;
use Src\Support\Domain\Models\Tenant;

/**
 * تكامل الواجهة: صفحة نشاط مستخدم فعلاً بتعرض نشاطه، معزولة بالمستأجر،
 * ومن غير N+1. (docs/11 بند ٨)
 */
function uiActivityContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

it('صفحة نشاط المستخدم بتعرض أحداثه', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create(['name' => 'قبل']);
    $target->tenants()->attach($tenant);
    $target->update(['name' => 'بعد']);

    uiActivityContext($tenant, $admin);

    $activities = Livewire::actingAs($admin)
        ->test(ListUserActivities::class, ['record' => $target->getKey()])
        ->assertOk()
        ->instance()
        ->getActivities();

    expect(collect($activities->items())->pluck('event'))->toContain('updated');
});

it('صفحة نشاط مستخدم مشترك بتعرض نشاط المستأجر الحالي بس', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $adminB = userWithRole('admin', $b);

    $shared = User::factory()->create(['name' => 'قبل']);
    $shared->tenants()->attach([$a->getKey(), $b->getKey()]);

    actingWithinTenant($a);
    $shared->update(['name' => 'اتعدّل في أ']);

    uiActivityContext($b, $adminB);
    $shared->update(['name' => 'اتعدّل في ب']);

    $activities = Livewire::actingAs($adminB)
        ->test(ListUserActivities::class, ['record' => $shared->getKey()])
        ->assertOk()
        ->instance()
        ->getActivities();

    $tenantIds = collect($activities->items())->map(fn ($item) => $item->properties['tenant_id'] ?? null);

    expect($tenantIds)->not->toContain($a->getKey())
        ->and($tenantIds)->toContain($b->getKey());
});

it('عرض النشاط مايعملش N+1 على causer', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    // كذا حدث بأكتر من ناشر (causer) — لو مفيش eager loading، عدد
    // الاستعلامات بيزيد مع عدد الأحداث
    for ($i = 0; $i < 5; $i++) {
        $target->update(['name' => "اسم {$i}"]);
    }

    uiActivityContext($tenant, $admin);

    // نتحقق إن causer محمّل مسبقاً (eager) لكل عنصر — لو مش محمّل، كل
    // وصول لـ `$activity->causer` هيعمل استعلام لوحده (N+1).
    $activities = Livewire::actingAs($admin)
        ->test(ListUserActivities::class, ['record' => $target->getKey()])
        ->instance()
        ->getActivities();

    expect($activities->items())->not->toBeEmpty();

    foreach ($activities->items() as $activity) {
        expect($activity->relationLoaded('causer'))->toBeTrue();
    }
});
