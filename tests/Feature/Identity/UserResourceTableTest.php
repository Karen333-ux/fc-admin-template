<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUsers;
use Src\Support\Domain\Models\Tenant;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function usersTableContext(Tenant $tenant, User $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

/** عضو في مستأجر، مع دور اختياري */
function tenantUser(Tenant $tenant, string $name, ?string $role = null): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->tenants()->attach($tenant);

    if ($role !== null) {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $user->assignRole($role);
        $registrar->forgetCachedPermissions();
    }

    return $user->fresh();
}

function usersTable(Tenant $tenant, User $admin): Table
{
    usersTableContext($tenant, $admin);

    return Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->instance()
        ->getTable();
}

// ────────────────────────────────────────────────────────────────
// الأداء — N+1 هو الخطر الحقيقي للعمود الجديد
// ────────────────────────────────────────────────────────────────

it('عمود الأدوار مابيعملش N+1 — الاستعلامات لسه ≤ ١٠', function (): void {
    // ميزانية استعلامات الصفحة بعد إضافة عمود علاقة. (docs/08 بند ٩ رقم ٣)
    //
    // ℹ️ ملاحظة أمانة: الاختبار ده **مش** حارس N+1 — Filament بيحمّل
    //    علاقات الأعمدة لوحده فالعدد بيفضل منضبط حتى من غير `with()`.
    //    عقد التحميل المسبق نفسه مثبّت في الاختبار اللي بعديه.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    foreach (range(1, 15) as $i) {
        tenantUser($tenant, "عضو {$i}", 'viewer');
    }

    usersTableContext($tenant, $admin);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->loadTable()
        ->assertOk();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(count($queries))->toBeLessThanOrEqual(
        10,
        'استعلامات صفحة الجدول: '.count($queries)."\n".
        implode("\n", array_map(static fn (array $q): string => $q['query'], $queries)),
    );
});

it('استعلام المورد بيحمّل الأدوار مسبقاً', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    usersTableContext($tenant, $admin);

    expect(UserResource::getEloquentQuery()->getEagerLoads())->toHaveKey('roles');
});

// ────────────────────────────────────────────────────────────────
// عزل المستأجرين — حارس تراجُع على ADR-016
// ────────────────────────────────────────────────────────────────

it('الفلتر الجديد مالمسش عزل المستأجرين', function (): void {
    // ⚠️ `getEloquentQuery()` اتعدّلت في الشريحة دي (التحميل المسبق).
    // فلتر العضوية لازم يفضل مكانه — مفيش شبكة أمان تحته. (ADR-016)
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $foreign = tenantUser($tenantA, 'مستخدم بعيد');
    $mine = tenantUser($tenantB, 'مستخدم قريب');

    $admin = userWithRole('admin', $tenantB);
    usersTableContext($tenantB, $admin);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$foreign]);
});

it('البحث الشامل لسه معزول بعد تعديل الاستعلام', function (): void {
    // البحث الشامل بيشارك نفس `getEloquentQuery()` (شريحة التنقّل)
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenantUser($tenantA, 'سرّية جداً');

    $intruder = userWithRole('admin', $tenantB);
    usersTableContext($tenantB, $intruder);

    expect(UserResource::getGlobalSearchResults('سرّية'))->toBeEmpty();
});

// ────────────────────────────────────────────────────────────────
// الفلاتر
// ────────────────────────────────────────────────────────────────

it('فلتر الأدوار وفلتر التاريخ موجودين', function (): void {
    $tenant = Tenant::factory()->create();
    $table = usersTable($tenant, userWithRole('admin', $tenant));

    expect(array_keys($table->getFilters()))->toBe(['roles', 'created_at']);
});

it('الفلتر المخصص بيطلّع مؤشر فيه القيمة المختارة', function (): void {
    // معيار قبول docs/08 بند ٩ رقم ٥: `indicateUsing()` إلزامية لأي فلتر
    // مخصص — من غيرها المستخدم بيفلتر، ينسى، وبعدين يبلّغ إن البيانات ناقصة.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $from = now()->subMonth()->toDateString();

    usersTableContext($tenant, $admin);

    $component = Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->loadTable()
        ->filterTable('created_at', ['from' => $from]);

    $indicators = $component->instance()
        ->getTable()
        ->getFilter('created_at')
        ->getIndicators();

    expect($indicators)->not->toBeEmpty();

    $labels = array_map(
        static fn (object $indicator): string => (string) $indicator->getLabel(),
        $indicators,
    );

    expect(implode(' | ', $labels))->toContain($from);
});

it('فلتر التاريخ بيفلتر فعلاً وبيعرض مؤشر', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    $old = tenantUser($tenant, 'قديم');
    $old->forceFill(['created_at' => now()->subYear()])->save();

    $recent = tenantUser($tenant, 'حديث');

    usersTableContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->loadTable()
        ->filterTable('created_at', ['from' => now()->subMonth()->toDateString()])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('تخطيط الفلاتر زي ما docs/08 بند ٣ بيقول', function (): void {
    $tenant = Tenant::factory()->create();
    $table = usersTable($tenant, userWithRole('admin', $tenant));

    expect($table->getFiltersLayout())->toBe(FiltersLayout::AboveContentCollapsible)
        ->and($table->getFiltersFormColumns())->toBe(3);
});

// ────────────────────────────────────────────────────────────────
// الترتيب والرابط والحالة الفارغة
// ────────────────────────────────────────────────────────────────

it('الترتيب الافتراضي محدد صراحةً', function (): void {
    $tenant = Tenant::factory()->create();
    $table = usersTable($tenant, userWithRole('admin', $tenant));

    expect($table->getDefaultSortColumn())->toBe('created_at')
        ->and($table->getDefaultSortDirection())->toBe('desc');
});

it('الحالة الفارغة بتفرّق بين مفيش بيانات والفلتر مارجّعش', function (): void {
    // معيار قبول docs/08 بند ٩ رقم ٩
    $tenant = Tenant::factory()->create();
    $table = usersTable($tenant, userWithRole('admin', $tenant));

    // مفيش فلتر → رسالة المستخدمين الخاصة
    expect($table->getEmptyStateHeading())->toBe(__('identity::identity.empty.heading'))
        ->and($table->getEmptyStateHeading())->not->toBe(__('table.empty.heading'));
});

it('الحالة الفارغة فيها زرار إنشاء مربوط بالـ Policy', function (): void {
    $tenant = Tenant::factory()->create();
    $table = usersTable($tenant, userWithRole('admin', $tenant));

    expect($table->getEmptyStateActions())->toHaveCount(1);
});

// ────────────────────────────────────────────────────────────────
// إعدادات الجدول العامة لسه واصلة
// ────────────────────────────────────────────────────────────────

it('افتراضيات الشريحة السابقة لسه متطبّقة', function (): void {
    // حارس تراجُع على b950448 — المورد بيدهس الحالة الفارغة بس
    $tenant = Tenant::factory()->create();
    $table = usersTable($tenant, userWithRole('admin', $tenant));

    expect($table->getDefaultPaginationPageOption())->toBe(25)
        ->and($table->getPaginationPageOptions())->toBe([10, 25, 50, 100])
        ->and($table->isStriped())->toBeTrue()
        ->and($table->isLoadingDeferred())->toBeTrue();
});

// ────────────────────────────────────────────────────────────────
// الترجمة
// ────────────────────────────────────────────────────────────────

it('نصوص الشريحة مترجمة في اللغتين', function (): void {
    $keys = [
        'identity::identity.fields.roles',
        'identity::identity.empty.heading',
        'identity::identity.empty.cta',
        'common.created_between',
        'table.empty.no_results',
    ];

    foreach ($keys as $key) {
        expect(trans($key, [], 'ar'))->not->toBe($key)
            ->and(trans($key, [], 'en'))->not->toBe($key)
            ->and(trans($key, [], 'ar'))->not->toBe(trans($key, [], 'en'));
    }
});
