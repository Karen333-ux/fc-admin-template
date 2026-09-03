<?php

declare(strict_types=1);

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Support\Components\Contracts\ScopedComponentManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUsers;
use Src\Support\Domain\Models\Tenant;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function tableContext(Tenant $tenant, User $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

/** جدول مورد حقيقي — مش `Table` مبني بالإيد */
function userResourceTable(Tenant $tenant, User $admin): Table
{
    tableContext($tenant, $admin);

    return Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->instance()
        ->getTable();
}

/** عضو في مستأجر */
function tenantMember(Tenant $tenant, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->tenants()->attach($tenant);

    return $user;
}

// ────────────────────────────────────────────────────────────────
// ١. الافتراضيات العامة بتوصل لجدول مورد حقيقي
// ────────────────────────────────────────────────────────────────

it('كل الافتراضيات العامة متطبّقة على جدول المورد', function (): void {
    // ⚠️ الاختبار على جدول `ListUsers` الحقيقي مش على `Table::make()` يدوي —
    // ده بيقيس المسار اللي المستخدم بيعدّي منه فعلاً.
    $tenant = Tenant::factory()->create();
    $table = userResourceTable($tenant, userWithRole('admin', $tenant));

    expect($table->getDefaultPaginationPageOption())->toBe(25)
        ->and($table->getPaginationPageOptions())->toBe([10, 25, 50, 100])
        ->and($table->hasExtremePaginationLinks())->toBeTrue()
        ->and($table->persistsFiltersInSession())->toBeTrue()
        ->and($table->persistsSortInSession())->toBeTrue()
        ->and($table->persistsSearchInSession())->toBeTrue()
        ->and($table->persistsColumnSearchesInSession())->toBeTrue()
        ->and($table->persistsColumnsInSession())->toBeTrue()
        ->and($table->isLoadingDeferred())->toBeTrue()
        ->and($table->hasDeferredFilters())->toBeTrue()
        ->and($table->isSearchOnBlur())->toBeTrue()
        ->and($table->isStriped())->toBeTrue()
        ->and($table->hasReorderableColumns())->toBeTrue()
        ->and($table->getEmptyStateIcon())->toBe(Heroicon::OutlinedInbox);
});

it('نصوص الحالة الفارغة العامة جاية من الترجمة مش مكتوبة في الكود', function (): void {
    // ⚠️ `UserResource` بقى بيدهس الحالة الفارغة عشان يفرّق بين «مفيش
    //    بيانات» و«الفلتر مارجّعش حاجة» (docs/08 بند ٧)، فمابيصلحش يبقى
    //    هو المجس للافتراضي العام. الجدول اللي من غير أي دهس هو المجس.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    tableContext($tenant, $admin);

    $livewire = Livewire::actingAs($admin)->test(ListUsers::class)->instance();
    $bare = Table::make($livewire);

    expect($bare->getEmptyStateHeading())->toBe(__('table.empty.heading'))
        ->and($bare->getEmptyStateDescription())->toBe(__('table.empty.description'));
});

it('دهس المورد للحالة الفارغة مقصود ومختلف عن العام', function (): void {
    // بيوثّق التفاعل بين الشريحتين: الافتراضي العام موجود، والمورد بيغلبه
    $tenant = Tenant::factory()->create();
    $table = userResourceTable($tenant, userWithRole('admin', $tenant));

    expect($table->getEmptyStateHeading())->not->toBe(__('table.empty.heading'))
        ->and($table->getEmptyStateHeading())->toBe(__('identity::identity.empty.heading'));
});

// ────────────────────────────────────────────────────────────────
// ٢. المورد بيغلب الافتراضي
// ────────────────────────────────────────────────────────────────

it('إعداد المورد بيغلب الافتراضي العام', function (): void {
    // ⚠️ ده ترتيب الإنتاج بالظبط: `Table::make()` بيطبّق الكلوجر العام،
    // وبعدها `table()` بتاع المورد بينادي الـ setters بتاعته. اللي بعدين
    // بيغلب.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    tableContext($tenant, $admin);

    $livewire = Livewire::actingAs($admin)->test(ListUsers::class)->instance();

    $overridden = Table::make($livewire)
        ->defaultPaginationPageOption(50)
        ->striped(false)
        ->deferLoading(false);

    expect($overridden->getDefaultPaginationPageOption())->toBe(50)
        ->and($overridden->isStriped())->toBeFalse()
        ->and($overridden->isLoadingDeferred())->toBeFalse()
        // واللي مااتغيّرش لسه جاي من الافتراضي العام
        ->and($overridden->hasExtremePaginationLinks())->toBeTrue();
});

// ────────────────────────────────────────────────────────────────
// ٣. إعدادات UserResource القائمة زي ما هي
// ────────────────────────────────────────────────────────────────

it('أعمدة UserResource زي ما هي و created_at لسه مخفي', function (): void {
    $tenant = Tenant::factory()->create();
    $table = userResourceTable($tenant, userWithRole('admin', $tenant));

    $columns = $table->getColumns();

    // `roles.name` اتضاف في شريحة ٣ (docs/08 بند ٢)
    expect(array_keys($columns))->toBe(['name', 'email', 'roles.name', 'created_at'])
        ->and($columns['created_at']->isToggledHiddenByDefault())->toBeTrue()
        ->and($columns['name']->isSearchable())->toBeTrue()
        ->and($columns['email']->isSearchable())->toBeTrue();
});

it('إجراءات UserResource زي ما هي', function (): void {
    $tenant = Tenant::factory()->create();
    $table = userResourceTable($tenant, userWithRole('admin', $tenant));

    $recordActions = array_map(
        static fn (object $action): string => $action::class,
        $table->getRecordActions(),
    );

    expect($recordActions)->toContain(ViewAction::class, EditAction::class);

    // الإجراء الجماعي لسه موجود جوّه المجموعة
    $bulk = collect($table->getToolbarActions())
        ->flatMap(static fn (object $action): array => method_exists($action, 'getActions')
            ? $action->getActions()
            : [$action])
        ->map(static fn (object $action): string => $action::class)
        ->all();

    expect($bulk)->toContain(DeleteBulkAction::class);
});

// ────────────────────────────────────────────────────────────────
// ٤. الصفوف لسه بتظهر مع التحميل المؤجّل
// ────────────────────────────────────────────────────────────────

it('الصفوف بتظهر بعد loadTable مع deferLoading', function (): void {
    // ⚠️ `deferLoading()` معناه إن أول رندر مافيهوش صفوف. من غير الاختبار ده
    // الشريحة كلها مش مرئية للسويت: اختبار عدد الاستعلامات بيعدّي حتى لو
    // الجدول مابيعرضش ولا صف.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $member = tenantMember($tenant, 'عضو ظاهر');

    tableContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$member]);
});

// ────────────────────────────────────────────────────────────────
// ٥. عزل المستأجرين لسه شغّال
// ────────────────────────────────────────────────────────────────

it('الجدول لسه معزول بين المستأجرين بعد الافتراضيات', function (): void {
    // حارس تراجُع على ADR-016 — الافتراضيات العامة مابتلمسش الاستعلام
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $foreign = tenantMember($tenantA, 'مستخدم بعيد');
    $mine = tenantMember($tenantB, 'مستخدم قريب');

    $admin = userWithRole('admin', $tenantB);
    tableContext($tenantB, $admin);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$foreign]);
});

// ────────────────────────────────────────────────────────────────
// ٦. عدد الاستعلامات ماتراجعش
// ────────────────────────────────────────────────────────────────

it('صفحة الجدول لسه ≤ ١٠ استعلامات بعد التحميل الكامل', function (): void {
    // حتى مع `loadTable()` (يعني الاستعلام المؤجّل اتنفّذ فعلاً)
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    User::factory()->count(15)->create()
        ->each(fn (User $u) => $u->tenants()->attach($tenant));

    tableContext($tenant, $admin);

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

// ────────────────────────────────────────────────────────────────
// ٧. التسجيل بيعيش أكتر من طلب
// ────────────────────────────────────────────────────────────────

it('الافتراضيات بتفضل متطبّقة على طلب تاني', function (): void {
    // ⚠️ `ComponentManager` الأساسي singleton، و`ScopedComponentManager`
    // نسخة لكل طلب. لو التسجيل اتعمل بعد ما النسخة المستنسخة تتحل، كان
    // هيضيع بين الطلبات. الاختبار ده بيفضّي النسخة المستنسخة — زي ما
    // بيحصل مع طلب جديد — ويتأكد إن الافتراضي لسه موجود.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    expect(userResourceTable($tenant, $admin)->getDefaultPaginationPageOption())->toBe(25);

    app()->forgetInstance(ScopedComponentManager::class);

    expect(userResourceTable($tenant, $admin)->getDefaultPaginationPageOption())->toBe(25);
});

// ────────────────────────────────────────────────────────────────
// ٨. الترجمة
// ────────────────────────────────────────────────────────────────

it('نصوص الحالة الفارغة موجودة في اللغتين ومختلفة', function (): void {
    foreach (['table.empty.heading', 'table.empty.description'] as $key) {
        expect(trans($key, [], 'ar'))->not->toBe($key)
            ->and(trans($key, [], 'en'))->not->toBe($key)
            ->and(trans($key, [], 'ar'))->not->toBe(trans($key, [], 'en'));
    }
});
